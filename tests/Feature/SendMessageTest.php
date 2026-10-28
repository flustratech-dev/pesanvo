<?php

namespace Tests\Feature;

use App\Jobs\SendMessageJob;
use App\Models\ApiKey;
use App\Models\Message;
use App\Models\WaSession;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendMessageTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private WaSession $session;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create([
            'name' => 'Contoh',
            'slug' => 'contoh',
            'max_sessions' => 2,
            'monthly_message_quota' => 5,
            'api_rate_limit_per_minute' => 1000,
        ]);

        $this->session = $this->workspace->sessions()->create([
            'name' => 'CS',
            'status' => 'connected',
            'phone_number' => '6281111111111',
        ]);

        [, $this->key] = ApiKey::issue($this->workspace, 'kunci uji');
    }

    /**
     * Tanpa session_id, gateway harus memilih sendiri sesi yang terhubung.
     *
     * Ini pernah gagal di produksi: sesi terlihat hijau di dashboard, tapi
     * penyaringan `kind` yang tidak tampak di antarmuka mana pun membuatnya
     * tidak pernah terpilih, dan pemanggil hanya menerima "Tidak ada sesi
     * WhatsApp yang bisa dipakai" tanpa petunjuk penyebabnya.
     */
    public function test_pesan_terkirim_tanpa_menyebut_sesi(): void
    {
        Queue::fake();

        $response = $this->withHeader('X-Api-Key', $this->key)
            ->postJson('/api/v1/messages/text', [
                'to' => '081234567890',
                'message' => 'Halo',
            ])
            ->assertStatus(202);

        $this->assertSame(
            $this->session->id,
            Message::find($response->json('data.id'))->wa_session_id
        );
    }

    public function test_pesan_teks_diantre_dan_nomor_dinormalisasi(): void
    {
        Queue::fake();

        $response = $this->withHeader('X-Api-Key', $this->key)
            ->postJson('/api/v1/messages/text', [
                'session_id' => $this->session->id,
                'to' => '0812-3456-7890',
                'message' => 'Halo',
            ])
            ->assertStatus(202);

        $message = Message::find($response->json('data.id'));

        $this->assertSame('6281234567890', $message->to_number);
        $this->assertSame('6281234567890@c.us', $message->chat_id);
        $this->assertSame('queued', $message->status);

        Queue::assertPushed(SendMessageJob::class);
    }

    public function test_nomor_tidak_valid_ditolak(): void
    {
        $this->withHeader('X-Api-Key', $this->key)
            ->postJson('/api/v1/messages/text', [
                'session_id' => $this->session->id,
                'to' => '123',
                'message' => 'Halo',
            ])
            ->assertStatus(422);
    }

    /**
     * Kuota dihitung saat pesan diantre, bukan saat terkirim. Kalau dihitung
     * belakangan, satu workspace bisa mengantrekan puluhan ribu pesan lebih dulu
     * dan baru ketahuan melewati batas setelah semuanya terlanjur terkirim.
     */
    public function test_kuota_habis_menolak_pesan_berikutnya(): void
    {
        Queue::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->withHeader('X-Api-Key', $this->key)
                ->postJson('/api/v1/messages/text', [
                    'session_id' => $this->session->id,
                    'to' => '081234567890',
                    'message' => "Pesan {$i}",
                ])
                ->assertStatus(202);
        }

        $this->withHeader('X-Api-Key', $this->key)
            ->postJson('/api/v1/messages/text', [
                'session_id' => $this->session->id,
                'to' => '081234567890',
                'message' => 'Yang keenam',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Kuota pesan bulan ini sudah habis (5 pesan).');
    }

    public function test_pengiriman_massal_melewatkan_nomor_rusak(): void
    {
        Queue::fake();

        $response = $this->withHeader('X-Api-Key', $this->key)
            ->postJson('/api/v1/messages/bulk', [
                'session_id' => $this->session->id,
                'to' => ['081234567890', 'bukan-nomor', '081298765432'],
                'message' => 'Pengumuman',
            ])
            ->assertStatus(202);

        $this->assertSame(2, $response->json('data.queued'));
        $this->assertCount(1, $response->json('data.rejected'));
    }

    public function test_job_menandai_pesan_terkirim_setelah_engine_menjawab(): void
    {
        Http::fake([
            'engine.test/sessions/*/messages' => Http::response(['wa_message_id' => 'true_628_ABC'], 200),
        ]);

        $this->withHeader('X-Api-Key', $this->key)
            ->postJson('/api/v1/messages/text', [
                'session_id' => $this->session->id,
                'to' => '081234567890',
                'message' => 'Halo',
            ])
            ->assertStatus(202);

        // QUEUE_CONNECTION=sync saat testing, jadi job sudah berjalan.
        $message = Message::first();

        $this->assertSame('sent', $message->status);
        $this->assertSame('true_628_ABC', $message->wa_message_id);
    }

    public function test_kegagalan_permanen_dari_engine_tidak_diulang(): void
    {
        Http::fake([
            'engine.test/sessions/*/messages' => Http::response(
                ['error' => 'Nomor 6281234567890 tidak terdaftar di WhatsApp.'],
                422
            ),
        ]);

        $this->withHeader('X-Api-Key', $this->key)
            ->postJson('/api/v1/messages/text', [
                'session_id' => $this->session->id,
                'to' => '081234567890',
                'message' => 'Halo',
            ]);

        $message = Message::first();

        $this->assertSame('failed', $message->status);
        $this->assertSame(1, $message->attempts);
        $this->assertStringContainsString('tidak terdaftar', $message->error);
    }

    /**
     * Sesi yang sedang putus bukan kegagalan permanen — nomornya masih tertaut,
     * hanya belum tersambung. Pesan sengaja dikembalikan ke antrean agar
     * terkirim begitu sesi pulih, bukan dibuang.
     */
    public function test_sesi_yang_tidak_terhubung_mengembalikan_pesan_ke_antrean(): void
    {
        $this->session->update(['status' => 'disconnected']);

        $this->withHeader('X-Api-Key', $this->key)
            ->postJson('/api/v1/messages/text', [
                'session_id' => $this->session->id,
                'to' => '081234567890',
                'message' => 'Halo',
            ])
            ->assertStatus(202);

        $message = Message::first();

        $this->assertSame('queued', $message->status);
        $this->assertStringContainsString('tidak terhubung', $message->error);
    }
}
