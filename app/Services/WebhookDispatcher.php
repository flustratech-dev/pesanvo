<?php

namespace App\Services;

use App\Jobs\DeliverWebhookJob;
use App\Models\Webhook;
use App\Models\Workspace;

/**
 * Memilih webhook mana yang menerima sebuah kejadian.
 *
 * Sampai webhook menempel di API key, pertanyaan ini tidak ada — seluruh
 * webhook workspace menerima segalanya. Sekarang satu API key = satu website =
 * satu webhook URL (pola Xendit), dan pemilihannya punya aturan yang berbeda
 * per jenis kejadian:
 *
 * | Kejadian | Dikirim ke |
 * |---|---|
 * | `message.status` | webhook milik API key yang MENGIRIM pesan itu; kalau tidak ada, webhook workspace |
 * | `message.received` | SELURUH webhook — workspace dan API key sekaligus |
 * | `session.*` | webhook workspace saja |
 *
 * Alasan `message.received` berbeda: pesan masuk tidak punya "pengirim" dari
 * pihak kami, jadi tidak ada dasar apa pun untuk memilih salah satu integrasi.
 * Mengirimkannya hanya ke satu webhook berarti menebak, dan tebakan yang salah
 * di sini berarti pesan pelanggan hilang tanpa jejak di sistem yang menunggunya.
 */
class WebhookDispatcher
{
    public const EVENT_MESSAGE_RECEIVED = 'message.received';

    public const EVENT_MESSAGE_STATUS = 'message.status';

    public const EVENT_SESSION_STATUS = 'session.status';

    public const EVENT_SESSION_QR = 'session.qr';

    /**
     * Mengantre satu kejadian ke webhook yang berhak menerimanya.
     *
     * `$apiKeyId` adalah API key **pemicu** kejadian, bukan penyaring: pesan
     * yang dikirim dari dashboard tidak punya API key, dan itu benar — statusnya
     * jatuh ke webhook workspace.
     *
     * Selalu lewat antrean supaya endpoint pelanggan yang lambat atau mati tidak
     * menahan permintaan yang sedang berjalan.
     */
    public function dispatch(Workspace $workspace, string $event, array $payload, ?int $apiKeyId = null): void
    {
        $webhooks = $workspace->webhooks()->where('is_active', true)->get()
            ->filter(fn (Webhook $webhook) => $webhook->listensTo($event))
            ->filter(fn (Webhook $webhook) => $this->berhak($webhook, $event, $apiKeyId));

        foreach ($webhooks as $webhook) {
            DeliverWebhookJob::dispatch($webhook->id, $event, $payload);
        }
    }

    /**
     * Apakah satu webhook berhak menerima kejadian ini.
     *
     * Webhook tingkat workspace (`api_key_id` null) menerima **segalanya** —
     * itu perilaku yang berlaku sebelum kolomnya ada, dan mempertahankannya
     * apa adanya adalah syarat mutlak: setiap baris webhook yang sudah ada di
     * produksi adalah baris seperti itu, dan mempersempitnya berarti memutus
     * integrasi yang sedang berjalan tanpa pelanggan tahu sampai ada pesan
     * yang hilang.
     */
    private function berhak(Webhook $webhook, string $event, ?int $apiKeyId): bool
    {
        if ($webhook->untukSeluruhWorkspace()) {
            return true;
        }

        return match ($event) {
            // Tidak punya pengirim dari pihak kami, jadi tidak ada dasar
            // memilih salah satu integrasi. Seluruhnya menerima.
            self::EVENT_MESSAGE_RECEIVED => true,

            // Hanya API key yang benar-benar mengirim pesannya. Status milik
            // API key A yang bocor ke webhook API key B membocorkan nomor
            // tujuan pelanggan ke integrasi yang tidak berhak melihatnya.
            self::EVENT_MESSAGE_STATUS => $apiKeyId !== null && $webhook->api_key_id === $apiKeyId,

            // Peristiwa siklus hidup sesi milik workspace, bukan milik satu
            // integrasi: nomor yang terputus memengaruhi seluruhnya sekaligus.
            default => false,
        };
    }
}
