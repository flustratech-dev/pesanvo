<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureWorkspaceSelected;
use App\Jobs\DeliverWebhookJob;
use App\Models\AuditLog;
use App\Models\WebhookDelivery;
use App\Services\WebhookDispatcher;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WebhookController extends Controller
{
    public function index(Request $request): View
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        return view('dashboard.webhooks.index', [
            'webhooks' => $workspace->webhooks()->with('apiKey')->latest()->get(),

            // Hanya kunci yang masih hidup boleh ditawarkan: menawarkan kunci
            // yang sudah dicabut berarti menawarkan webhook yang tidak akan
            // pernah menerima apa pun.
            'apiKeys' => $workspace->apiKeys()->whereNull('revoked_at')->orderBy('name')->get(),
            'deliveries' => WebhookDelivery::whereIn('webhook_id', $workspace->webhooks()->pluck('id'))
                ->latest()
                ->limit(20)
                ->get(),
            'availableEvents' => [
                WebhookDispatcher::EVENT_MESSAGE_RECEIVED => 'Pesan masuk',
                WebhookDispatcher::EVENT_MESSAGE_STATUS => 'Status pengiriman berubah',
                WebhookDispatcher::EVENT_SESSION_STATUS => 'Status sesi berubah',
                WebhookDispatcher::EVENT_SESSION_QR => 'QR baru tersedia',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        $data = $request->validate([
            'url' => ['required', 'url:http,https', 'max:500'],
            // Wajib milik workspace ini. Tanpa penjagaan itu, id kunci milik
            // workspace lain bisa dititipkan lewat form dan webhook-nya
            // menerima kejadian yang bukan haknya.
            'api_key_id' => ['nullable', 'integer', Rule::exists('api_keys', 'id')
                ->where('workspace_id', $workspace->id)
                ->whereNull('revoked_at')],
            'events' => ['nullable', 'array'],
            'events.*' => [Rule::in([
                WebhookDispatcher::EVENT_MESSAGE_RECEIVED,
                WebhookDispatcher::EVENT_MESSAGE_STATUS,
                WebhookDispatcher::EVENT_SESSION_STATUS,
                WebhookDispatcher::EVENT_SESSION_QR,
            ])],
        ]);

        $webhook = $workspace->webhooks()->create([
            'url' => $data['url'],
            'api_key_id' => $data['api_key_id'] ?? null,
            'events' => $data['events'] ?? null,
            'secret' => Str::random(48),
        ]);

        AuditLog::record('webhook.created', $webhook, [
            'url' => $webhook->url,
            'api_key_id' => $webhook->api_key_id,
        ]);

        return back()->with('status', 'Webhook ditambahkan. Simpan signing secret-nya untuk memverifikasi payload.');
    }

    /**
     * Mengirim payload contoh supaya workspace bisa memastikan endpoint mereka
     * menerima dan memverifikasi tanda tangan dengan benar sebelum ada trafik
     * sungguhan.
     */
    public function test(Request $request, int $id): RedirectResponse
    {
        $webhook = EnsureWorkspaceSelected::from($request)->webhooks()->findOrFail($id);

        DeliverWebhookJob::dispatch($webhook->id, 'webhook.test', [
            'message' => 'Ini kiriman uji coba dari Pesanvo Gateway.',
            'sent_at' => now()->toIso8601String(),
        ]);

        return back()->with('status', 'Kiriman uji coba diantre. Cek daftar pengiriman beberapa detik lagi.');
    }

    public function toggle(Request $request, int $id): RedirectResponse
    {
        $webhook = EnsureWorkspaceSelected::from($request)->webhooks()->findOrFail($id);

        $webhook->update([
            'is_active' => ! $webhook->is_active,
            // Menyalakan ulang setelah dinonaktifkan otomatis harus mengosongkan
            // hitungan gagal, kalau tidak ia langsung mati lagi.
            'consecutive_failures' => 0,
        ]);

        return back()->with('status', $webhook->is_active ? 'Webhook diaktifkan.' : 'Webhook dinonaktifkan.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $webhook = EnsureWorkspaceSelected::from($request)->webhooks()->findOrFail($id);

        AuditLog::record('webhook.deleted', $webhook, ['url' => $webhook->url]);
        $webhook->delete();

        return back()->with('status', 'Webhook dihapus.');
    }
}
