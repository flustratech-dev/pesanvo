<?php

namespace Tests\Feature;

use App\Jobs\DeliverWebhookJob;
use App\Models\ApiKey;
use App\Models\Message;
use App\Models\User;
use App\Models\WaSession;
use App\Models\Webhook;
use App\Models\Workspace;
use App\Services\MessageDispatcher;
use App\Services\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Ke mana sebuah kejadian dikirim.
 *
 * Yang paling penting di berkas ini adalah tes pertama: **webhook lama tetap
 * menerima semuanya.** Setiap baris webhook yang sudah ada di produksi punya
 * `api_key_id` null, dan mempersempit haknya berarti memutus integrasi yang
 * sedang berjalan di sisi pelanggan — kegagalan yang tidak mereka sadari sampai
 * ada pesan yang hilang, dan saat itu jejaknya sudah dingin.
 */
class WebhookRoutingTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private WaSession $session;

    private ApiKey $kunciA;

    private ApiKey $kunciB;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        Queue::fake();

        $pemilik = User::create([
            'name' => 'Owner',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Toko Uji',
            'slug' => 'toko-uji',
            'owner_id' => $pemilik->id,
            'owner_email' => $pemilik->email,
            'max_sessions' => 3,
            'monthly_message_quota' => 1000,
        ]);

        $this->berlangganan($this->workspace);

        $this->session = $this->workspace->sessions()->create([
            'name' => 'Nomor Utama',
            'status' => 'connected',
            'driver' => 'wwebjs',
        ]);

        [$this->kunciA] = ApiKey::issue($this->workspace, 'Toko Online');
        [$this->kunciB] = ApiKey::issue($this->workspace, 'Aplikasi Kasir');
    }

    private function webhook(?int $apiKeyId, array $events = []): Webhook
    {
        return $this->workspace->webhooks()->create([
            'url' => 'https://contoh.id/hook/'.($apiKeyId ?? 'workspace'),
            'api_key_id' => $apiKeyId,
            'events' => $events ?: null,
            'secret' => 'rahasia',
            'is_active' => true,
        ]);
    }

    /** @return array<int, int> id webhook yang menerima kiriman */
    private function penerima(): array
    {
        $id = [];

        foreach (Queue::pushedJobs()[DeliverWebhookJob::class] ?? [] as $antrean) {
            $id[] = $antrean['job']->webhookId;
        }

        return $id;
    }

    /**
     * Syarat mutlak migrasi ini.
     *
     * Baris tanpa `api_key_id` adalah SELURUH baris yang ada di produksi
     * sekarang. Kalau salah satu kejadian berhenti sampai ke sana, migrasinya
     * memutus integrasi yang sedang berjalan.
     */
    public function test_webhook_lama_tetap_menerima_semua_kejadian(): void
    {
        $lama = $this->webhook(null);
        $dispatcher = app(WebhookDispatcher::class);

        foreach ([
            WebhookDispatcher::EVENT_MESSAGE_STATUS,
            WebhookDispatcher::EVENT_MESSAGE_RECEIVED,
            WebhookDispatcher::EVENT_SESSION_STATUS,
            WebhookDispatcher::EVENT_SESSION_QR,
        ] as $event) {
            Queue::fake();

            // Dikirim DENGAN api key pemicu: webhook workspace tetap harus
            // menerimanya, bukan cuma saat pemicunya kosong.
            $dispatcher->dispatch($this->workspace, $event, [], $this->kunciA->id);

            $this->assertContains($lama->id, $this->penerima(), "Webhook workspace tidak menerima {$event}.");
        }
    }

    public function test_status_dari_kunci_a_tidak_sampai_ke_webhook_kunci_b(): void
    {
        $a = $this->webhook($this->kunciA->id);
        $b = $this->webhook($this->kunciB->id);

        app(WebhookDispatcher::class)->dispatch(
            $this->workspace,
            WebhookDispatcher::EVENT_MESSAGE_STATUS,
            [],
            $this->kunciA->id,
        );

        $penerima = $this->penerima();

        $this->assertContains($a->id, $penerima);
        $this->assertNotContains($b->id, $penerima, 'Status milik API key lain bocor ke webhook ini.');
    }

    public function test_pesan_dari_dashboard_sampai_ke_webhook_workspace(): void
    {
        $workspaceHook = $this->webhook(null);
        $kunciHook = $this->webhook($this->kunciA->id);

        // Tanpa API key pemicu — persis keadaan pesan yang dikirim dari
        // dashboard, dan itu memang benar: ia tidak berasal dari integrasi mana
        // pun.
        app(WebhookDispatcher::class)->dispatch(
            $this->workspace,
            WebhookDispatcher::EVENT_MESSAGE_STATUS,
            [],
            null,
        );

        $penerima = $this->penerima();

        $this->assertContains($workspaceHook->id, $penerima);
        $this->assertNotContains($kunciHook->id, $penerima);
    }

    /**
     * Pesan masuk tidak punya "pengirim" dari pihak kami, jadi tidak ada dasar
     * memilih salah satu integrasi. Menebak di sini berarti pesan pelanggan
     * hilang tanpa jejak di sistem yang menunggunya.
     */
    public function test_pesan_masuk_sampai_ke_seluruh_webhook(): void
    {
        $workspaceHook = $this->webhook(null);
        $a = $this->webhook($this->kunciA->id);
        $b = $this->webhook($this->kunciB->id);

        app(WebhookDispatcher::class)->dispatch(
            $this->workspace,
            WebhookDispatcher::EVENT_MESSAGE_RECEIVED,
            [],
        );

        $penerima = $this->penerima();

        foreach ([$workspaceHook, $a, $b] as $hook) {
            $this->assertContains($hook->id, $penerima);
        }
    }

    /** Nomor yang terputus memengaruhi seluruh integrasi, bukan salah satu. */
    public function test_kejadian_sesi_hanya_ke_webhook_workspace(): void
    {
        $workspaceHook = $this->webhook(null);
        $kunciHook = $this->webhook($this->kunciA->id);

        app(WebhookDispatcher::class)->dispatch(
            $this->workspace,
            WebhookDispatcher::EVENT_SESSION_STATUS,
            [],
        );

        $penerima = $this->penerima();

        $this->assertContains($workspaceHook->id, $penerima);
        $this->assertNotContains($kunciHook->id, $penerima);
    }

    /**
     * API key tersimpan di baris pesan saat ANTRE.
     *
     * Pengiriman terjadi di dalam job, dan di sana tidak ada request — jadi
     * tanpa kolom ini, `message.status` tidak punya cara tahu integrasi mana
     * yang berhak menerimanya.
     */
    public function test_api_key_tersimpan_di_baris_pesan_saat_dikirim_lewat_api(): void
    {
        $this->withHeader('Authorization', 'Bearer '.ApiKey::issue($this->workspace, 'Uji')[1])
            ->postJson('/api/v1/messages/text', [
                'session_id' => $this->session->id,
                'to' => '6281234567890',
                'message' => 'halo',
            ])->assertStatus(202);

        $this->assertNotNull(Message::latest()->first()->api_key_id);
    }

    public function test_pesan_dari_dashboard_tidak_punya_api_key(): void
    {
        app(MessageDispatcher::class)->queue($this->session, '6281234567890', [
            'type' => 'text',
            'body' => 'halo',
        ]);

        $this->assertNull(Message::latest()->first()->api_key_id);
    }

    /**
     * Kunci yang dicabut tidak boleh menghapus webhook-nya.
     *
     * Pelanggan kehilangan konfigurasi yang susah payah dipasang, dan yang
     * hilang bukan cuma URL-nya melainkan signing secret yang sudah tertanam di
     * aplikasi mereka. Webhook-nya turun jadi tingkat workspace.
     */
    public function test_api_key_dihapus_menurunkan_webhooknya_jadi_tingkat_workspace(): void
    {
        $hook = $this->webhook($this->kunciA->id);

        $this->kunciA->delete();

        $this->assertNull($hook->fresh()->api_key_id);
        $this->assertTrue($hook->fresh()->untukSeluruhWorkspace());
    }
}
