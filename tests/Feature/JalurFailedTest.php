<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WaSession;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Jalur `failed` — lubang yang baru terlihat dari produksi 8 September 2026.
 *
 * `SyncSessionStatusJob` hanya menyentuh sesi `disconnected`, jadi seluruh
 * penjagaan percobaan yang dibangun di sana TIDAK BERLAKU untuk sesi `failed`.
 * Yang terekam di server: dua sesi berstatus `failed` punya Chromium hidup, dan
 * salah satunya beranak satu proses tiap menit.
 *
 * Dua jalur bisa menyalakan sesi `failed` tanpa lewat penjadwal sama sekali:
 *
 *   `BootstrapController` memulihkan `failed` yang `connected_at`-nya terisi,
 *   dan `supervisi_engine` di start.sh menjalankan ulang engine tiga detik
 *   setelah ia mati — jadi engine yang tidak stabil memanggilnya berulang kali.
 *
 *   Tombol Hubungkan, yang memang harus selalu boleh.
 *
 * Keduanya sekarang ikut menghitung.
 */
class JalurFailedTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $pemilik = User::create([
            'name' => 'Owner',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Contoh',
            'slug' => 'contoh',
            'owner_id' => $pemilik->id,
            'owner_email' => $pemilik->email,
            'max_sessions' => 2,
            'status' => 'active',
        ]);
    }

    private function sesi(array $tambahan = []): WaSession
    {
        return $this->workspace->sessions()->create(array_merge([
            'name' => 'CS',
            'driver' => 'wwebjs',
            'status' => 'failed',
            'connected_at' => now()->subDay(),
        ], $tambahan));
    }

    /**
     * `getJson()` sengaja TIDAK dipakai: ia mengirim body `[]` walau untuk GET,
     * dan tanda tangan HMAC dihitung atas isi body — jadi seluruh permintaan
     * ditolak 401 dengan alasan yang tidak ada hubungannya dengan yang diuji.
     * Engine sungguhan mengirim GET tanpa body sama sekali.
     */
    private function bootstrap(): array
    {
        return $this->call(
            'GET',
            '/internal/engine/bootstrap',
            [],
            [],
            [],
            $this->serverHeaders($this->tandaTangan())
        )->json('data') ?? [];
    }

    /** Tanda tangan HMAC yang sama dengan yang dipakai engine. */
    private function tandaTangan(string $isi = ''): array
    {
        $stempel = (string) now()->getTimestamp();

        return [
            'X-Engine-Timestamp' => $stempel,
            'X-Engine-Signature' => hash_hmac('sha256', $stempel.'.'.$isi, config('gateway.engine.hmac_secret')),
        ];
    }

    public function test_pemulihan_saat_engine_boot_ikut_menghitung_percobaan(): void
    {
        $sesi = $this->sesi();

        $this->assertSame(0, $sesi->refresh()->connect_failures);

        $this->bootstrap();

        $this->assertSame(
            1,
            $sesi->refresh()->connect_failures,
            'Menyerahkan sesi ke engine adalah percobaan penyambungan dan harus terhitung; '
                .'tanpa itu, engine yang tidak stabil memulai badai percobaan tanpa rem.'
        );
    }

    public function test_sesi_yang_sudah_melewati_ambang_tidak_lagi_dipulihkan_saat_boot(): void
    {
        $sesi = $this->sesi(['connect_failures' => WaSession::BATAS_PERCOBAAN_SAMBUNG]);

        $daftar = $this->bootstrap();

        $this->assertSame(
            [],
            $daftar,
            'Sesi yang sudah berulang kali gagal tetap diserahkan ke engine setiap boot.'
        );
    }

    public function test_engine_yang_dijalankan_ulang_berkali_kali_akhirnya_berhenti_sendiri(): void
    {
        $sesi = $this->sesi();

        // Persis yang terjadi kalau engine tidak stabil: start.sh menjalankannya
        // ulang, dan tiap kali ia menanyakan daftar sesi yang harus dipulihkan.
        for ($i = 0; $i < WaSession::BATAS_PERCOBAAN_SAMBUNG + 4; $i++) {
            $this->bootstrap();
        }

        $this->assertSame(
            WaSession::BATAS_PERCOBAAN_SAMBUNG,
            $sesi->refresh()->connect_failures,
            'Penghitung terus naik; berarti sesinya masih diserahkan ke engine.'
        );

        $this->assertSame([], $this->bootstrap());
    }

    public function test_kegagalan_yang_dilaporkan_engine_ikut_menghitung(): void
    {
        $sesi = $this->sesi(['status' => 'connecting']);

        $isi = json_encode([
            'session_id' => $sesi->id,
            'event' => 'auth_failure',
            'payload' => ['message' => 'Execution context was destroyed.'],
        ]);

        $this->call(
            'POST',
            '/internal/engine/events',
            [],
            [],
            [],
            $this->serverHeaders($this->tandaTangan($isi)),
            $isi
        )->assertOk();

        $sesi->refresh();

        $this->assertSame('failed', $sesi->status);
        $this->assertSame(
            1,
            $sesi->connect_failures,
            'Kegagalan yang dimulai di luar penjadwal tidak terhitung di mana pun.'
        );
    }

    public function test_sesi_yang_akhirnya_tersambung_mengembalikan_penghitung_ke_nol(): void
    {
        $sesi = $this->sesi(['connect_failures' => WaSession::BATAS_PERCOBAAN_SAMBUNG - 1]);

        $isi = json_encode([
            'session_id' => $sesi->id,
            'event' => 'ready',
            'payload' => ['phone_number' => '628123456789', 'push_name' => 'Contoh'],
        ]);

        $this->call(
            'POST',
            '/internal/engine/events',
            [],
            [],
            [],
            $this->serverHeaders($this->tandaTangan($isi)),
            $isi
        )->assertOk();

        $this->assertSame(0, $sesi->refresh()->connect_failures);

        // Dan sesudah itu ia layak dipulihkan lagi saat engine boot berikutnya.
        $this->assertCount(1, $this->bootstrap());
    }

    /** @param  array<string, string>  $headers */
    private function serverHeaders(array $headers): array
    {
        $hasil = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        foreach ($headers as $nama => $nilai) {
            $hasil['HTTP_'.str_replace('-', '_', strtoupper($nama))] = $nilai;
        }

        return $hasil;
    }
}
