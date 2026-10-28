<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\WaSession;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngineCallbackTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private WaSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'Contoh', 'slug' => 'contoh', 'max_sessions' => 2]);
        $this->session = $this->workspace->sessions()->create(['name' => 'CS', 'status' => 'connecting']);
    }

    /** Menandatangani body persis seperti yang dilakukan engine Node. */
    private function signed(array $body): array
    {
        $json = json_encode($body);
        $timestamp = (string) now()->getTimestamp();

        return [
            'X-Engine-Timestamp' => $timestamp,
            'X-Engine-Signature' => hash_hmac('sha256', "{$timestamp}.{$json}", config('gateway.engine.hmac_secret')),
        ];
    }

    private function postEvent(array $body)
    {
        // Body dikirim mentah supaya byte-nya sama persis dengan yang
        // ditandatangani — json_encode ulang oleh helper test bisa menghasilkan
        // urutan atau escaping yang berbeda dan membuat tanda tangan meleset.
        return $this->call(
            'POST',
            '/internal/engine/events',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($this->signed($body) + ['Content-Type' => 'application/json', 'Accept' => 'application/json']),
            json_encode($body)
        );
    }

    public function test_callback_tanpa_tanda_tangan_ditolak(): void
    {
        $this->postJson('/internal/engine/events', [
            'session_id' => $this->session->id,
            'event' => 'ready',
        ])->assertStatus(401);
    }

    public function test_tanda_tangan_salah_ditolak(): void
    {
        $body = ['session_id' => $this->session->id, 'event' => 'ready'];
        $timestamp = (string) now()->getTimestamp();

        $this->call(
            'POST',
            '/internal/engine/events',
            [],
            [],
            [],
            $this->transformHeadersToServerVars([
                'X-Engine-Timestamp' => $timestamp,
                'X-Engine-Signature' => str_repeat('a', 64),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]),
            json_encode($body)
        )->assertStatus(401);
    }

    public function test_tanda_tangan_kedaluwarsa_ditolak(): void
    {
        $body = ['session_id' => $this->session->id, 'event' => 'ready'];
        $json = json_encode($body);
        $timestamp = (string) now()->subHour()->getTimestamp();

        $this->call(
            'POST',
            '/internal/engine/events',
            [],
            [],
            [],
            $this->transformHeadersToServerVars([
                'X-Engine-Timestamp' => $timestamp,
                'X-Engine-Signature' => hash_hmac('sha256', "{$timestamp}.{$json}", config('gateway.engine.hmac_secret')),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]),
            $json
        )->assertStatus(401);
    }

    public function test_event_ready_menandai_sesi_terhubung(): void
    {
        $this->postEvent([
            'session_id' => $this->session->id,
            'event' => 'ready',
            'payload' => ['phone_number' => '6281111111111', 'push_name' => 'Toko Contoh'],
        ])->assertOk();

        $this->session->refresh();

        $this->assertSame('connected', $this->session->status);
        $this->assertSame('6281111111111', $this->session->phone_number);
        $this->assertSame('Toko Contoh', $this->session->push_name);
    }

    public function test_event_qr_menyimpan_gambar_dan_masa_berlaku(): void
    {
        $this->postEvent([
            'session_id' => $this->session->id,
            'event' => 'qr',
            'payload' => ['qr_image' => 'data:image/png;base64,AAAA'],
        ])->assertOk();

        $this->session->refresh();

        $this->assertSame('qr', $this->session->status);
        $this->assertSame('data:image/png;base64,AAAA', $this->session->qr_payload);
        $this->assertTrue($this->session->hasFreshQr());
    }

    /**
     * whatsapp-web.js menerbitkan QR baru dengan jeda tidak tetap, sampai
     * sekitar 60 detik. Masa berlaku yang lebih pendek dari itu membuat QR
     * dianggap kedaluwarsa sebelum penggantinya datang, dan modal di dashboard
     * mendadak kosong tepat saat pengguna hendak men-scan.
     */
    public function test_qr_masih_berlaku_setelah_jeda_terlama_antar_penerbitan(): void
    {
        $this->postEvent([
            'session_id' => $this->session->id,
            'event' => 'qr',
            'payload' => ['qr_image' => 'data:image/png;base64,AAAA'],
        ])->assertOk();

        $this->travel(61)->seconds();

        $this->assertTrue(
            $this->session->fresh()->hasFreshQr(),
            'QR sudah dianggap kedaluwarsa padahal engine belum tentu mengirim penggantinya.'
        );
    }

    /**
     * Pesan masuk ikut berhenti saat langganan mati.
     *
     * Sebelum ini cuma pengiriman keluar yang ditutup, sementara pesan masuk
     * tetap dicatat dan tetap diteruskan ke webhook — integrasi pelanggan tetap
     * berjalan separuh tanpa satu rupiah pun dibayar, dan separuh itu justru
     * yang paling banyak dipakai orang yang memakai gateway ini untuk mencatat
     * percakapan ke CRM.
     */
    public function test_pesan_masuk_diabaikan_saat_langganan_mati(): void
    {
        $this->session->update(['status' => 'connected', 'phone_number' => '6281111111111']);
        $this->workspace->forceFill(['status' => 'suspended'])->save();

        // Engine tetap dijawab 200: membalas galat membuatnya mengulang
        // peristiwa yang sama berkali-kali untuk keadaan yang tidak akan
        // berubah sampai ada yang membayar.
        $this->postEvent([
            'session_id' => $this->session->id,
            'event' => 'message',
            'payload' => [
                'wa_message_id' => 'false_628_XYZ',
                'chat_id' => '6289999999999@c.us',
                'from' => '6289999999999',
                'type' => 'text',
                'body' => 'Halo min',
            ],
        ])->assertOk();

        $this->assertSame(0, Message::where('direction', 'inbound')->count());
        $this->assertSame(0, $this->workspace->fresh()->currentUsage()->messages_received);
    }

    /** Masa berlaku yang lewat juga menutup, tanpa menunggu job harian. */
    public function test_pesan_masuk_diabaikan_saat_masa_berlaku_lewat(): void
    {
        $this->session->update(['status' => 'connected', 'phone_number' => '6281111111111']);
        $this->workspace->forceFill([
            'status' => 'active',
            'service_until' => now()->subMinute(),
        ])->save();

        $this->postEvent([
            'session_id' => $this->session->id,
            'event' => 'message',
            'payload' => [
                'wa_message_id' => 'false_628_ABC',
                'chat_id' => '6289999999999@c.us',
                'from' => '6289999999999',
                'type' => 'text',
                'body' => 'Halo',
            ],
        ])->assertOk();

        $this->assertSame(0, Message::where('direction', 'inbound')->count());
    }

    public function test_pesan_masuk_tercatat_dan_menaikkan_hitungan(): void
    {
        $this->session->update(['status' => 'connected', 'phone_number' => '6281111111111']);

        $this->postEvent([
            'session_id' => $this->session->id,
            'event' => 'message',
            'payload' => [
                'wa_message_id' => 'false_628_XYZ',
                'chat_id' => '6289999999999@c.us',
                'from' => '6289999999999',
                'type' => 'text',
                'body' => 'Halo min',
            ],
        ])->assertOk();

        $message = Message::where('direction', 'inbound')->first();

        $this->assertNotNull($message);
        $this->assertSame('6289999999999', $message->from_number);
        $this->assertSame(1, $this->workspace->currentUsage()->messages_received);
    }

    /**
     * Ack dari WhatsApp bisa datang tidak berurutan. Tanpa penjagaan, pesan
     * yang sudah "read" bisa turun lagi jadi "delivered".
     */
    public function test_status_pesan_tidak_pernah_mundur(): void
    {
        $message = Message::create([
            'workspace_id' => $this->workspace->id,
            'wa_session_id' => $this->session->id,
            'direction' => 'outbound',
            'wa_message_id' => 'true_628_ABC',
            'to_number' => '6289999999999',
            'type' => 'text',
            'body' => 'Halo',
            'status' => 'sent',
        ]);

        $this->postEvent([
            'session_id' => $this->session->id,
            'event' => 'message_ack',
            'payload' => ['wa_message_id' => 'true_628_ABC', 'ack' => 3],
        ])->assertOk();

        $this->assertSame('read', $message->fresh()->status);

        // Ack lama menyusul setelahnya.
        $this->postEvent([
            'session_id' => $this->session->id,
            'event' => 'message_ack',
            'payload' => ['wa_message_id' => 'true_628_ABC', 'ack' => 2],
        ])->assertOk();

        $this->assertSame('read', $message->fresh()->status);
    }

    /**
     * Engine mengirim `wa_message_id` dari `message.id?._serialized`, yang bisa
     * bernilai null. Laravel menerjemahkan `orWhere('wa_message_id', null)`
     * menjadi `orWhereNull(...)` — dan itu cocok dengan pesan mana pun yang
     * belum punya id WhatsApp, yaitu pesan yang masih mengantre.
     *
     * Akibatnya satu ack tanpa id bisa menandai pesan yang belum terkirim
     * sebagai sudah dibaca, dan pesan aslinya tidak pernah diperbarui.
     */
    public function test_ack_tanpa_id_tidak_menyentuh_pesan_yang_masih_mengantre(): void
    {
        $mengantre = Message::create([
            'workspace_id' => $this->workspace->id,
            'wa_session_id' => $this->session->id,
            'direction' => 'outbound',
            'wa_message_id' => null,
            'to_number' => '6289999999999',
            'type' => 'text',
            'body' => 'Belum terkirim',
            'status' => 'queued',
        ]);

        $this->postEvent([
            'session_id' => $this->session->id,
            'event' => 'message_ack',
            'payload' => ['ack' => 3],
        ])->assertOk();

        $this->assertSame(
            'queued',
            $mengantre->fresh()->status,
            'Pesan yang masih mengantre ikut ditandai terbaca oleh ack tanpa id.'
        );
    }

    public function test_event_untuk_sesi_yang_sudah_dihapus_meminta_engine_berhenti(): void
    {
        $this->postEvent([
            'session_id' => '01JQQQQQQQQQQQQQQQQQQQQQQQ',
            'event' => 'ready',
        ])->assertOk()->assertJsonPath('action', 'stop_session');
    }
}
