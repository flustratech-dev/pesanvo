<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PaymentGatewayLog;
use App\Services\Billing\SubscriptionService;
use App\Services\Payment\MayarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MayarWebhookController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly MayarService $mayar,
    ) {}

    /**
     * Menangani callback webhook dari Mayar.id.
     *
     * Webhook adalah satu-satunya penentu kebenaran (source of truth) status
     * transaksi. Jika pembayaran sukses diterima di Mayar, tagihan ditandai
     * lunas dan paket atau saldo pelanggan seketika diaktifkan.
     */
    public function handle(Request $request): JsonResponse
    {
        // 1. Validasi Token / Secret jika diatur di konfigurasi
        $configuredToken = $this->mayar->getWebhookToken() ?? config('services.mayar.webhook_token');
        if (filled($configuredToken)) {
            $token = $request->header('x-mayar-token')
                ?? $request->header('x-callback-token')
                ?? $request->header('x-mayar-secret')
                ?? $request->header('Authorization')
                ?? $request->input('token')
                ?? $request->query('token');

            if (str_starts_with((string) $token, 'Bearer ')) {
                $token = substr((string) $token, 7);
            }

            $signature = $request->header('x-mayar-signature')
                ?? $request->header('x-signature')
                ?? $request->header('signature');

            $valid = false;

            if ($token && hash_equals($configuredToken, (string) $token)) {
                $valid = true;
            } elseif ($signature) {
                $computed = hash_hmac('sha256', $request->getContent(), $configuredToken);
                if (hash_equals($computed, (string) $signature)) {
                    $valid = true;
                }
            }

            if (! $valid) {
                Log::warning('Mayar webhook ditolak: token/signature tidak valid.', [
                    'ip' => $request->ip(),
                ]);

                return response()->json(['status' => 'error', 'message' => 'Unauthorized token or signature'], 401);
            }
        }

        $payload = $request->all();
        $event = $request->input('event') ?? $request->input('event.received') ?? $request->input('type');
        $data = $request->input('data') ?? [];

        // 2. Simpan audit log gateway masuk
        $log = PaymentGatewayLog::create([
            'gateway' => 'mayar',
            'event' => (string) $event,
            'reference_id' => $data['id'] ?? null,
            'status' => 'pending',
            'payload' => $payload,
        ]);

        // Tanggapi uji coba (Testing URL) dari dashboard Mayar
        if (in_array($event, ['testing', 'test', 'ping'], true)) {
            $log->update(['status' => 'processed']);

            return response()->json([
                'statusCode' => 200,
                'messages' => 'Webhook test successful',
                'status' => 'success',
            ]);
        }

        // 3. Hanya proses event pembayaran sukses
        if ($event !== 'payment.received') {
            $log->update(['status' => 'ignored']);

            return response()->json([
                'status' => 'ignored',
                'message' => "Event '{$event}' diabaikan.",
            ]);
        }

        // 4. Cari tagihan berdasarkan extraData.invoice_id atau Mayar payment reference
        $invoiceId = $data['extraData']['invoice_id'] ?? null;
        $invoice = null;

        if ($invoiceId) {
            $invoice = Invoice::find($invoiceId);
        }

        if (! $invoice && filled($data['id'] ?? null)) {
            $invoice = Invoice::where('payment_reference', $data['id'])->first();
        }

        if (! $invoice && filled($data['extraData']['invoice_number'] ?? null)) {
            $invoice = Invoice::where('number', $data['extraData']['invoice_number'])->first();
        }

        if (! $invoice) {
            $msg = 'Tagihan tidak ditemukan untuk referensi Mayar: '.($data['id'] ?? 'null');
            Log::warning($msg, ['data' => $data]);
            $log->update([
                'status' => 'failed',
                'error_message' => $msg,
            ]);

            return response()->json(['status' => 'not_found', 'message' => $msg], 200);
        }

        $log->update(['invoice_id' => $invoice->id]);

        // 5. Idempotency Guard: jika sudah lunas, jangan proses ulang
        if ($invoice->isPaid()) {
            $log->update(['status' => 'processed']);

            return response()->json([
                'status' => 'already_paid',
                'message' => "Tagihan {$invoice->number} sudah berstatus lunas sebelumnya.",
            ]);
        }

        // 6. Eksekusi Pelunasan via SubscriptionService
        try {
            $mayarId = $data['id'] ?? $invoice->payment_reference ?? 'N/A';
            $channel = $data['paymentMethod'] ?? $data['payment_method'] ?? 'mayar';

            $invoice->forceFill([
                'channel' => 'mayar',
                'payment_reference' => $mayarId,
                'payment_payload' => $data,
            ])->save();

            $this->subscriptions->markPaid(
                invoice: $invoice,
                admin: null,
                note: "Lunas otomatis via Mayar. Ref: {$mayarId} [{$channel}]",
            );

            $log->update(['status' => 'processed']);

            Log::info("Tagihan {$invoice->number} berhasil dilunasi via webhook Mayar.", [
                'invoice_id' => $invoice->id,
                'mayar_id' => $mayarId,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => "Tagihan {$invoice->number} berhasil dilunasi.",
            ]);
        } catch (\Throwable $e) {
            Log::error("Gagal melunasi tagihan {$invoice->number} dari webhook Mayar: ".$e->getMessage(), [
                'exception' => $e,
            ]);

            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memproses pelunasan: '.$e->getMessage(),
            ], 500);
        }
    }
}
