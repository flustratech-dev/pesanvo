<?php

namespace App\Jobs;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\Notifications\Notifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DeliverWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public readonly int $webhookId,
        public readonly string $event,
        public readonly array $payload,
    ) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 300];
    }

    public function handle(): void
    {
        $webhook = Webhook::find($this->webhookId);

        if (! $webhook || ! $webhook->is_active) {
            return;
        }

        $body = [
            'event' => $this->event,
            'timestamp' => now()->toIso8601String(),
            'delivery_id' => (string) Str::ulid(),
            'data' => $this->payload,
        ];

        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $delivery = WebhookDelivery::create([
            'webhook_id' => $webhook->id,
            'event' => $this->event,
            'payload' => $body,
            'attempts' => $this->attempts(),
        ]);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                // Workspace memverifikasi header ini dengan secret mereka untuk
                // memastikan payload benar-benar datang dari Pesanvo.
                'X-Pesanvo-Signature' => hash_hmac('sha256', $json, $webhook->secret),
                'X-Pesanvo-Event' => $this->event,
            ])->timeout(15)->withBody($json, 'application/json')->post($webhook->url);
        } catch (\Throwable $e) {
            $this->markFailure($webhook, $delivery, null, $e->getMessage());

            throw $e;
        }

        if ($response->successful()) {
            $delivery->update([
                'response_code' => $response->status(),
                'response_body' => Str::limit($response->body(), 2000),
                'delivered_at' => now(),
            ]);

            $webhook->update([
                'last_success_at' => now(),
                'consecutive_failures' => 0,
            ]);

            return;
        }

        $this->markFailure($webhook, $delivery, $response->status(), Str::limit($response->body(), 2000));

        if ($this->attempts() < $this->tries) {
            $this->release($this->backoff()[min($this->attempts() - 1, 1)]);
        }
    }

    private function markFailure(Webhook $webhook, WebhookDelivery $delivery, ?int $code, string $body): void
    {
        $delivery->update(['response_code' => $code, 'response_body' => $body]);

        $failures = $webhook->consecutive_failures + 1;

        $webhook->update([
            'last_failure_at' => now(),
            'consecutive_failures' => $failures,
            // Endpoint yang mati berhari-hari dinonaktifkan otomatis, supaya
            // antrean tidak terus terisi kiriman yang pasti gagal.
            'is_active' => $failures < 50,
        ]);

        /*
         | Dinonaktifkan otomatis TIDAK boleh terjadi diam-diam.
         |
         | Sebelum ini, webhook pelanggan berhenti sendiri setelah 50 kegagalan
         | tanpa satu pun kabar — dan yang menemukannya adalah orang yang
         | bertanya kenapa integrasinya berhenti menerima apa pun berminggu-
         | minggu kemudian. Peringatan di angka 25 memberi ruang memperbaiki
         | sebelum berhenti terjadi.
        */
        if (! $webhook->workspace) {
            return;
        }

        if ($failures >= 50) {
            app(Notifier::class)->keWorkspace(
                workspace: $webhook->workspace,
                type: 'webhook.disabled',
                title: 'Webhook dinonaktifkan otomatis',
                body: $webhook->url.' gagal 50 kali berturut-turut, jadi kami berhenti mengirim ke sana. '
                    .'Perbaiki endpoint-nya lalu aktifkan lagi dari menu Webhooks.',
                url: route('webhooks.index'),
                level: 'danger',
                dedupe: "webhook-disabled:{$webhook->id}:{$failures}",
            );
        } elseif ($failures === 25) {
            app(Notifier::class)->keWorkspace(
                workspace: $webhook->workspace,
                type: 'webhook.failing',
                title: 'Webhook gagal 25 kali berturut-turut',
                body: $webhook->url.' belum menjawab. Setelah 50 kegagalan kami menonaktifkannya otomatis.',
                url: route('webhooks.index'),
                level: 'warning',
                dedupe: "webhook-failing:{$webhook->id}:{$failures}",
            );
        }
    }
}
