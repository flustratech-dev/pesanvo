<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\Billing\BalanceService;
use App\Services\MessageDispatcher;
use App\Services\Providers\ProviderException;
use App\Services\Providers\ProviderManager;
use App\Services\WebhookDispatcher;
use App\Support\EngineError;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 120;

    public function __construct(public readonly string $messageId) {}

    /**
     * Jeda antar percobaan naik bertahap. Sesi yang baru putus biasanya butuh
     * puluhan detik untuk tersambung lagi, jadi mengulang secepat mungkin hanya
     * membakar jatah retry tanpa hasil.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(ProviderManager $providers, MessageDispatcher $dispatcher, WebhookDispatcher $webhooks): void
    {
        $message = Message::with('session.workspace')->find($this->messageId);

        if (! $message || $message->status !== 'queued') {
            // Sudah terkirim, dibatalkan, atau dipangkas retensi.
            return;
        }

        $session = $message->session;

        if (! $session) {
            $this->fail($message, 'Sesi pengirim sudah dihapus.', $dispatcher, $webhooks);

            return;
        }

        /*
         | Diperiksa lagi di sini, bukan hanya saat diantrekan.
         |
         | Antara antre dan kirim bisa lewat berjam-jam: tiap pesan ditahan
         | jeda anti-ban, antrean berjalan per sesi, dan job yang gagal
         | diulang dengan backoff. Broadcast beberapa ribu nomor yang
         | diantrekan menit terakhir sebelum langganan habis dulu tetap
         | terkirim seluruhnya setelah layanannya mati — tanpa satu pun
         | pemeriksaan di antaranya, karena penjagaan kuota dan status hanya
         | berjalan di `MessageDispatcher::queue()`.
         |
         | Ditandai gagal, bukan dilepas untuk diulang: langganan tidak akan
         | hidup kembali dalam tiga kali backoff, dan pesan yang menggantung
         | di antrean berhari-hari lalu tiba-tiba terkirim saat pembayaran
         | masuk jauh lebih buruk daripada pesan yang jelas-jelas gagal.
        */
        $workspace = $session->workspace;

        if ($workspace && ! $workspace->isActive()) {
            $this->fail($message, $workspace->serviceExpired()
                ? 'Masa berlaku langganan habis sebelum pesan ini sempat terkirim.'
                : 'Workspace sedang tidak aktif saat pesan ini akan dikirim.', $dispatcher, $webhooks);

            return;
        }

        /*
         | Saldo diperiksa lagi di sini, bukan cuma saat diantrekan.
         |
         | Alasannya sama dengan pemeriksaan status di atas: antara antre dan
         | kirim bisa lewat berjam-jam. Bedanya, di sini yang berubah bukan
         | tanggal melainkan saldo — dan yang menghabiskannya bisa jadi pesan
         | lain dari workspace yang sama yang kebetulan terkirim lebih dulu.
         |
         | Ditandai gagal, bukan dilepas untuk diulang: saldo tidak akan terisi
         | dalam tiga kali backoff, dan pesan yang menggantung berhari-hari lalu
         | tiba-tiba terkirim saat pelanggan mengisi saldo jauh lebih buruk
         | daripada pesan yang jelas-jelas gagal.
        */
        if ($workspace && $workspace->isPayg() && ! $workspace->isExempt()
            && (int) $workspace->balance < (int) config('billing.payg.price_per_message')) {
            $this->fail($message, 'Saldo habis sebelum pesan ini sempat terkirim.', $dispatcher, $webhooks);

            return;
        }

        $message->update(['status' => 'sending', 'attempts' => $message->attempts + 1]);

        try {
            $result = $providers->for($session)->send($session, $message);
        } catch (ProviderException $e) {
            $pesanError = EngineError::pesan($e);

            if (! $e->retryable || $this->attempts() >= $this->tries) {
                $this->fail($message, $pesanError, $dispatcher, $webhooks);

                return;
            }

            $message->update(['status' => 'queued', 'error' => $pesanError]);

            $this->release($this->backoff()[min($this->attempts() - 1, 2)]);

            return;
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim pesan WhatsApp', [
                'message_id' => $message->id,
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            $pesanError = EngineError::pesan($e);

            if ($this->attempts() >= $this->tries) {
                $this->fail($message, $pesanError, $dispatcher, $webhooks);

                return;
            }

            $message->update(['status' => 'queued', 'error' => $pesanError]);

            throw $e;
        }

        $message->update([
            'status' => 'sent',
            'wa_message_id' => $result['wa_message_id'],
            'provider_response' => $result['raw'],
            'sent_at' => now(),
            'error' => null,
        ]);

        /*
         | Saldo dipotong SETELAH pesannya benar-benar terkirim, bukan saat
         | diantrekan. Pesan yang gagal karena nomornya tidak terdaftar tidak
         | boleh memotong saldo pelanggan.
         |
         | Kegagalan pemotongan tidak boleh menjatuhkan job ini: pesannya sudah
         | terkirim dan tidak bisa ditarik kembali, jadi melempar galat di sini
         | cuma membuat job diulang dan penerima menerima pesan yang sama dua
         | kali. Yang benar adalah mencatatnya keras-keras — selisih saldo
         | terbaca di `rekonsiliasi()` dan muncul di halaman Sistem.
        */
        if ($workspace && $workspace->isPayg() && ! $workspace->isExempt()) {
            try {
                app(BalanceService::class)->chargeMessage($workspace, $message);
            } catch (\Throwable $e) {
                Log::error('Gagal memotong saldo untuk pesan yang sudah terkirim.', [
                    'message_id' => $message->id,
                    'workspace_id' => $workspace->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // API key dibaca dari BARIS PESAN, bukan dari request: di dalam job
        // tidak ada request sama sekali, dan pesan ini bisa saja diantrekan
        // berjam-jam yang lalu.
        $webhooks->dispatch($session->workspace, WebhookDispatcher::EVENT_MESSAGE_STATUS, [
            'message_id' => $message->id,
            'status' => 'sent',
            'to' => $message->to_number,
        ], $message->api_key_id);
    }

    private function fail(Message $message, string $error, MessageDispatcher $dispatcher, WebhookDispatcher $webhooks): void
    {
        $message->update(['status' => 'failed', 'error' => $error]);

        if ($workspace = $message->workspace) {
            $dispatcher->incrementUsage($workspace, 'messages_failed');

            $webhooks->dispatch($workspace, WebhookDispatcher::EVENT_MESSAGE_STATUS, [
                'message_id' => $message->id,
                'status' => 'failed',
                'error' => $error,
                'to' => $message->to_number,
            ], $message->api_key_id);
        }
    }
}
