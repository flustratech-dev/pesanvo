<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Modal QR tidak boleh menunggu selamanya.
 *
 * Baris di database hanya seakurat callback terakhir yang berhasil sampai dari
 * engine. Satu event `ready` yang hilang — Laravel kebetulan sedang restart,
 * jaringan antar container tersendat — dulu berarti modal menampilkan
 * "Menyiapkan sesi, mohon tunggu…" tanpa akhir, padahal nomornya sudah tertaut
 * sejak tadi. Tidak ada pesan galat, tidak ada batas waktu, tidak ada petunjuk.
 */
class SessionQrPollingTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'name' => 'Owner',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Contoh',
            'slug' => 'contoh',
            'owner_id' => $user->id,
            'max_sessions' => 2,
        ]);

        $this->workspace->members()->attach($user->id, ['role' => 'owner']);

        $this->actingAs($user);
        $this->withSession(['current_workspace_id' => $this->workspace->id]);
    }

    public function test_status_menanyakan_engine_saat_sesi_belum_selesai(): void
    {
        $session = $this->workspace->sessions()->create([
            'name' => 'CS',
            'driver' => 'wwebjs',
            'status' => 'connecting',
        ]);

        // Engine sudah tahu sesinya siap; Laravel belum, karena event `ready`
        // tidak pernah sampai.
        Http::fake(['*/sessions/*/status' => Http::response([
            'status' => 'connected',
            'phone_number' => '6281234567890',
            'push_name' => 'Toko Uji',
        ])]);

        $this->getJson(route('sessions.status', $session->id))
            ->assertOk()
            ->assertJsonPath('status', 'connected')
            ->assertJsonPath('phone_number', '6281234567890');

        $session->refresh();

        $this->assertSame('connected', $session->status);
        $this->assertNotNull($session->connected_at);
    }

    public function test_progres_penarikan_riwayat_diteruskan_ke_dashboard(): void
    {
        $session = $this->workspace->sessions()->create([
            'name' => 'CS',
            'driver' => 'wwebjs',
            'status' => 'connecting',
        ]);

        Http::fake(['*/sessions/*/status' => Http::response([
            'status' => 'connecting',
            'loading_percent' => 42,
        ])]);

        $this->getJson(route('sessions.status', $session->id))
            ->assertOk()
            ->assertJsonPath('status', 'connecting')
            ->assertJsonPath('loading_percent', 42);
    }

    /**
     * Engine yang tidak terjangkau tidak boleh menggagalkan polling — dashboard
     * cukup menampilkan keadaan terakhir yang diketahui dan mencoba lagi tiga
     * detik kemudian.
     */
    public function test_engine_tak_terjangkau_tetap_menjawab_keadaan_terakhir(): void
    {
        $session = $this->workspace->sessions()->create([
            'name' => 'CS',
            'driver' => 'wwebjs',
            'status' => 'qr',
            'qr_payload' => 'data:image/png;base64,AAAA',
            'qr_expires_at' => now()->addMinute(),
        ]);

        Http::fake(fn () => throw new ConnectionException('engine mati'));

        $this->getJson(route('sessions.status', $session->id))
            ->assertOk()
            ->assertJsonPath('qr', 'data:image/png;base64,AAAA');
    }

    /**
     * Sesi yang sudah tersambung tidak perlu ditanyakan lagi tiap tiga detik —
     * jawabannya tidak akan berubah tanpa ada aksi baru.
     */
    public function test_sesi_yang_sudah_tersambung_tidak_menanyai_engine(): void
    {
        $session = $this->workspace->sessions()->create([
            'name' => 'CS',
            'driver' => 'wwebjs',
            'status' => 'connected',
            'phone_number' => '6281234567890',
            'connected_at' => now(),
        ]);

        Http::fake();

        $this->getJson(route('sessions.status', $session->id))->assertOk();

        Http::assertNothingSent();
    }

    public function test_status_mengambil_qr_langsung_dari_engine_jika_qr_payload_database_belum_terisi(): void
    {
        $session = $this->workspace->sessions()->create([
            'name' => 'CS',
            'driver' => 'wwebjs',
            'status' => 'connecting',
            'qr_payload' => null,
        ]);

        Http::fake(['*/sessions/*/status' => Http::response([
            'status' => 'qr',
            'qr' => 'data:image/png;base64,LIVEQR',
        ])]);

        $this->getJson(route('sessions.status', $session->id))
            ->assertOk()
            ->assertJsonPath('status', 'qr')
            ->assertJsonPath('qr', 'data:image/png;base64,LIVEQR');

        $session->refresh();
        $this->assertSame('qr', $session->status);
        $this->assertSame('data:image/png;base64,LIVEQR', $session->qr_payload);
    }
}
