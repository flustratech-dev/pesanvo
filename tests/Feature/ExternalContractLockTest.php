<?php

namespace Tests\Feature;

use App\Jobs\DeliverWebhookJob;
use App\Models\ApiKey;
use App\Models\Message;
use App\Models\WaSession;
use App\Models\Webhook;
use App\Models\Workspace;
use App\Services\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExternalContractLockTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private WaSession $session;

    private string $key;

    private Webhook $webhook;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create([
            'name' => 'Bisnis Eksternal',
            'slug' => 'bisnis-eksternal',
            'max_sessions' => 5,
            'monthly_message_quota' => 1000,
            'api_rate_limit_per_minute' => 1000,
        ]);

        $this->session = $this->workspace->sessions()->create([
            'name' => 'Nomor Utama',
            'status' => 'connected',
            'phone_number' => '6281111111111',
            'connected_at' => now(),
        ]);

        [, $this->key] = ApiKey::issue($this->workspace, 'kunci produksi');

        $this->webhook = $this->workspace->webhooks()->create([
            'url' => 'https://webhook.site/uji-kontrak',
            'secret' => 'rahasia-webhook-12345',
            'events' => [
                WebhookDispatcher::EVENT_MESSAGE_RECEIVED,
                WebhookDispatcher::EVENT_MESSAGE_STATUS,
            ],
            'is_active' => true,
        ]);
    }

    private function postEvent(array $body)
    {
        $json = json_encode($body);
        $timestamp = (string) now()->getTimestamp();
        $headers = [
            'X-Engine-Timestamp' => $timestamp,
            'X-Engine-Signature' => hash_hmac('sha256', "{$timestamp}.{$json}", config('gateway.engine.hmac_secret')),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        return $this->call(
            'POST',
            '/internal/engine/events',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($headers),
            $json
        );
    }

    /**
     * Kontrak 1: chat_id untuk chat personal HARUS selalu <nomor>@c.us,
     * bahkan jika engine mengirimkan format Baileys @s.whatsapp.net.
     * Tidak boleh bocor ke database ataupun webhook.
     */
    public function test_chat_id_personal_terkunci_ke_format_c_us(): void
    {
        Queue::fake();

        // 1. Uji bila engine mengirim format Baileys (@s.whatsapp.net)
        $this->postEvent([
            'session_id' => $this->session->id,
            'event' => 'message',
            'payload' => [
                'wa_message_id' => '3EB01234567890ABCDEF',
                'chat_id' => '6289999999999@s.whatsapp.net',
                'from' => '6289999999999@s.whatsapp.net',
                'type' => 'text',
                'body' => 'Halo dari pelanggan personal',
            ],
        ])->assertOk();

        $message = Message::where('wa_message_id', '3EB01234567890ABCDEF')->first();
        $this->assertNotNull($message);

        // Database WAJIB menyimpan @c.us
        $this->assertSame('6289999999999@c.us', $message->chat_id);
        $this->assertSame('6289999999999', $message->from_number);

        // Payload webhook yang diantrekan WAJIB memuat @c.us dan is_group = false
        Queue::assertPushed(DeliverWebhookJob::class, function (DeliverWebhookJob $job) {
            if ($job->event !== WebhookDispatcher::EVENT_MESSAGE_RECEIVED) {
                return false;
            }

            $data = $job->payload;

            return $data['chat_id'] === '6289999999999@c.us'
                && $data['from'] === '6289999999999'
                && $data['is_group'] === false
                && $data['body'] === 'Halo dari pelanggan personal';
        });
    }

    /**
     * Kontrak 1 (lanjutan): chat_id untuk grup HARUS tetap <id>@g.us
     * dan is_group = true di payload webhook.
     */
    public function test_chat_id_grup_terkunci_ke_format_g_us(): void
    {
        Queue::fake();

        $this->postEvent([
            'session_id' => $this->session->id,
            'event' => 'message',
            'payload' => [
                'wa_message_id' => '3EB0GROUP1234567890',
                'chat_id' => '120363028392819283@g.us',
                'from' => '6281234567890@s.whatsapp.net',
                'type' => 'text',
                'body' => 'Pesan dari grup diskusi',
            ],
        ])->assertOk();

        $message = Message::where('wa_message_id', '3EB0GROUP1234567890')->first();
        $this->assertNotNull($message);

        $this->assertSame('120363028392819283@g.us', $message->chat_id);
        $this->assertSame('6281234567890', $message->from_number);

        Queue::assertPushed(DeliverWebhookJob::class, function (DeliverWebhookJob $job) {
            if ($job->event !== WebhookDispatcher::EVENT_MESSAGE_RECEIVED) {
                return false;
            }

            $data = $job->payload;

            return $data['chat_id'] === '120363028392819283@g.us'
                && $data['from'] === '6281234567890'
                && $data['is_group'] === true
                && $data['body'] === 'Pesan dari grup diskusi';
        });
    }

    /**
     * Kontrak 2: wa_message_id adalah string buram (opaque identifier) yang stabil,
     * tersimpan utuh di database, dan disajikan di respons API v1 GET /messages/{id}.
     */
    public function test_wa_message_id_buram_stabil_di_api_v1_dan_database(): void
    {
        $waId = 'BAE59283746102938475';

        $message = Message::create([
            'workspace_id' => $this->workspace->id,
            'wa_session_id' => $this->session->id,
            'direction' => 'outbound',
            'wa_message_id' => $waId,
            'to_number' => '6289999999999',
            'chat_id' => '6289999999999@c.us',
            'type' => 'text',
            'body' => 'Pesan uji wa_message_id',
            'status' => 'sent',
        ]);

        $response = $this->withHeader('X-Api-Key', $this->key)
            ->getJson("/api/v1/messages/{$message->id}")
            ->assertOk();

        $this->assertSame($waId, $response->json('data.wa_message_id'));
        $this->assertSame('6289999999999', $response->json('data.to'));
        $this->assertSame('sent', $response->json('data.status'));
    }

    /**
     * Kontrak 3: message_ack (1=sent, 2=delivered, 3=read) memperbarui status
     * pesan dan memicu webhook message.status dengan status yang identik.
     */
    public function test_message_ack_1_2_3_memetakan_status_dan_memicu_webhook(): void
    {
        $waId = '3EB0_ACK_TEST_123';

        $message = Message::create([
            'workspace_id' => $this->workspace->id,
            'wa_session_id' => $this->session->id,
            'direction' => 'outbound',
            'wa_message_id' => $waId,
            'to_number' => '6289999999999',
            'chat_id' => '6289999999999@c.us',
            'type' => 'text',
            'body' => 'Pesan uji ACK',
            'status' => 'queued',
        ]);

        // 1. ACK = 1 -> sent
        Queue::fake();
        $this->postEvent([
            'session_id' => $this->session->id,
            'event' => 'message_ack',
            'payload' => ['wa_message_id' => $waId, 'ack' => 1],
        ])->assertOk();

        $this->assertSame('sent', $message->fresh()->status);
        Queue::assertPushed(DeliverWebhookJob::class, function (DeliverWebhookJob $job) {
            return $job->event === WebhookDispatcher::EVENT_MESSAGE_STATUS
                && $job->payload['status'] === 'sent'
                && $job->payload['to'] === '6289999999999';
        });

        // 2. ACK = 2 -> delivered
        Queue::fake();
        $this->postEvent([
            'session_id' => $this->session->id,
            'event' => 'message_ack',
            'payload' => ['wa_message_id' => $waId, 'ack' => 2],
        ])->assertOk();

        $this->assertSame('delivered', $message->fresh()->status);
        $this->assertNotNull($message->fresh()->delivered_at);
        Queue::assertPushed(DeliverWebhookJob::class, function (DeliverWebhookJob $job) {
            return $job->event === WebhookDispatcher::EVENT_MESSAGE_STATUS
                && $job->payload['status'] === 'delivered';
        });

        // 3. ACK = 3 -> read
        Queue::fake();
        $this->postEvent([
            'session_id' => $this->session->id,
            'event' => 'message_ack',
            'payload' => ['wa_message_id' => $waId, 'ack' => 3],
        ])->assertOk();

        $this->assertSame('read', $message->fresh()->status);
        $this->assertNotNull($message->fresh()->read_at);
        Queue::assertPushed(DeliverWebhookJob::class, function (DeliverWebhookJob $job) {
            return $job->event === WebhookDispatcher::EVENT_MESSAGE_STATUS
                && $job->payload['status'] === 'read';
        });

        // 4. Status tidak boleh mundur saat ack lama tiba terlambat
        Queue::fake();
        $this->postEvent([
            'session_id' => $this->session->id,
            'event' => 'message_ack',
            'payload' => ['wa_message_id' => $waId, 'ack' => 2],
        ])->assertOk();

        $this->assertSame('read', $message->fresh()->status);
        Queue::assertNothingPushed();
    }
}
