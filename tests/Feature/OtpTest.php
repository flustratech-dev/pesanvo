<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Kode verifikasi dikirim dari nomor workspace pemanggil, bukan dari satu nomor
 * pusat.
 *
 * Dulu seluruh OTP keluar lewat sesi yang ditunjuk OTP_SESSION_ID dengan teks
 * yang menyebut "Pesanvo". Endpoint-nya terbuka untuk API key mana pun yang
 * punya scope `otp`, jadi pelanggan yang memakainya mengirim kode dari nomor
 * kami, atas nama kami, dan memotong kuota kami — sementara penerimanya melihat
 * merek yang sama sekali bukan merek yang mereka daftarkan.
 */
class OtpTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create([
            'name' => 'Toko Mawar',
            'slug' => 'toko-mawar',
            'max_sessions' => 2,
            'monthly_message_quota' => 100,
            'api_rate_limit_per_minute' => 1000,
        ]);

        [, $this->key] = ApiKey::issue($this->workspace, 'kunci uji', ['*', 'otp']);
    }

    public function test_kode_dikirim_dari_nomor_workspace_pemanggil(): void
    {
        Queue::fake();

        $session = $this->workspace->sessions()->create([
            'name' => 'CS',
            'status' => 'connected',
            'phone_number' => '6281111111111',
        ]);

        $this->withHeader('X-Api-Key', $this->key)
            ->postJson('/api/v1/otp/send', ['phone' => '081234567890'])
            ->assertStatus(202);

        $pesan = Message::first();

        $this->assertSame($session->id, $pesan->wa_session_id);
        $this->assertSame($this->workspace->id, $pesan->workspace_id);
    }

    public function test_teks_kode_memakai_nama_workspace_bukan_nama_platform(): void
    {
        Queue::fake();

        $this->workspace->sessions()->create([
            'name' => 'CS',
            'status' => 'connected',
            'phone_number' => '6281111111111',
        ]);

        $this->withHeader('X-Api-Key', $this->key)
            ->postJson('/api/v1/otp/send', ['phone' => '081234567890'])
            ->assertStatus(202);

        $body = Message::first()->body;

        $this->assertStringContainsString('Toko Mawar', $body);
        $this->assertStringNotContainsString('Pesanvo', $body);
    }

    /**
     * Tanpa nomor terhubung, jawabannya harus menyebut apa yang kurang. Pesan
     * lama menyuruh mengisi OTP_SESSION_ID di .env — instruksi yang mustahil
     * dijalankan pelanggan, karena .env itu milik kami, bukan milik mereka.
     */
    public function test_batas_laju_tetap_dijawab_429(): void
    {
        Queue::fake();

        $this->workspace->sessions()->create([
            'name' => 'CS',
            'status' => 'connected',
            'phone_number' => '6281111111111',
        ]);

        $this->withHeader('X-Api-Key', $this->key)
            ->postJson('/api/v1/otp/send', ['phone' => '081234567890'])
            ->assertStatus(202);

        $this->withHeader('X-Api-Key', $this->key)
            ->postJson('/api/v1/otp/send', ['phone' => '081234567890'])
            ->assertStatus(429);
    }

    public function test_workspace_tanpa_nomor_terhubung_diberi_tahu_apa_yang_kurang(): void
    {
        Queue::fake();

        $response = $this->withHeader('X-Api-Key', $this->key)
            ->postJson('/api/v1/otp/send', ['phone' => '081234567890'])
            ->assertStatus(422);

        $this->assertStringContainsString('Hubungkan satu nomor', $response->json('error.message'));
    }
}
