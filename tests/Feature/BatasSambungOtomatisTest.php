<?php

namespace Tests\Feature;

use App\Jobs\SyncSessionStatusJob;
use App\Models\Notification;
use App\Models\User;
use App\Models\WaSession;
use App\Models\Workspace;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Penjadwal tidak boleh mencoba menyambungkan satu sesi selamanya.
 *
 * `SyncSessionStatusJob` berjalan tiap menit. Selama penyebab putusnya
 * sementara, mencoba lagi memang yang diinginkan — itu yang membuat nomor
 * tersambung sendiri setelah redeploy tanpa siapa pun menekan apa pun. Yang
 * tidak diantisipasi adalah penyebab yang TIDAK akan membaik sendiri: di situ
 * loop ini menyalakan satu Chromium ±400 MB tiap menit, selamanya, dan itulah
 * yang menghabiskan memori server pada 7 September 2026.
 *
 * Jalur terburuknya tidak menghasilkan galat sama sekali. Engine menjawab
 * `start` dengan 200 seketika, lalu mengabarkan kegagalannya lewat event yang
 * boleh hilang. Karena itu yang dihitung PERCOBAAN, bukan kegagalan terlapor.
 */
class BatasSambungOtomatisTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $pemilik;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pemilik = User::create([
            'name' => 'Owner',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Contoh',
            'slug' => 'contoh',
            'owner_id' => $this->pemilik->id,
            'owner_email' => $this->pemilik->email,
            'max_sessions' => 2,
            'status' => 'active',
        ]);

        $this->workspace->members()->attach($this->pemilik->id, ['role' => 'owner']);
    }

    private function sesiTerputus(array $tambahan = []): WaSession
    {
        return $this->workspace->sessions()->create(array_merge([
            'name' => 'CS',
            'driver' => 'wwebjs',
            'status' => 'disconnected',
            'connected_at' => now()->subDay(),
        ], $tambahan));
    }

    /**
     * Engine yang selalu menjawab "sesi tidak berjalan" lalu menerima start
     * dengan 200 — persis keadaan di mana event kegagalannya hilang.
     */
    private function engineDiam(): void
    {
        Http::fake([
            '*/status' => Http::response(['status' => 'disconnected'], 404),
            '*/start' => Http::response(['status' => 'starting'], 200),
            '*' => Http::response([], 200),
        ]);
    }

    public function test_percobaan_berhenti_setelah_ambang(): void
    {
        $this->engineDiam();

        $session = $this->sesiTerputus();

        // Jauh lebih banyak dari ambang: kalau batasnya tidak berlaku, seluruh
        // sepuluh giliran akan menyalakan Chromium.
        for ($i = 0; $i < WaSession::BATAS_PERCOBAAN_SAMBUNG + 5; $i++) {
            (new SyncSessionStatusJob)->handle(app(SessionService::class));
        }

        $session->refresh();

        $this->assertSame(
            WaSession::BATAS_PERCOBAAN_SAMBUNG,
            $session->connect_failures,
            'Penghitung harus berhenti tepat di ambang, bukan terus naik.'
        );

        $start = collect(Http::recorded())
            ->filter(fn ($pasangan) => str_contains($pasangan[0]->url(), '/start'))
            ->count();

        $this->assertSame(
            WaSession::BATAS_PERCOBAAN_SAMBUNG,
            $start,
            "Engine dipanggil {$start} kali untuk menjalankan sesi; batasnya "
                .WaSession::BATAS_PERCOBAAN_SAMBUNG.'. Tiap panggilan berlebih adalah satu Chromium.'
        );
    }

    public function test_sesi_yang_menyerah_tidak_ditandai_failed(): void
    {
        $this->engineDiam();

        $session = $this->sesiTerputus();

        for ($i = 0; $i < WaSession::BATAS_PERCOBAAN_SAMBUNG + 3; $i++) {
            (new SyncSessionStatusJob)->handle(app(SessionService::class));
        }

        $session->refresh();

        // `failed` mengeluarkan sesi dari pemulihan otomatis BootstrapController.
        // Sesi yang kredensialnya masih utuh tidak boleh kehilangan pemulihan
        // gara-gara rentetan kegagalan yang mungkin sudah lewat.
        $this->assertSame('disconnected', $session->status);
    }

    public function test_nomor_yang_berhenti_dicoba_mengatakan_kenapa(): void
    {
        $this->engineDiam();

        $session = $this->sesiTerputus();

        for ($i = 0; $i < WaSession::BATAS_PERCOBAAN_SAMBUNG + 3; $i++) {
            (new SyncSessionStatusJob)->handle(app(SessionService::class));
        }

        $session->refresh();

        $this->assertStringContainsString('berhenti mencoba', (string) $session->last_error);
        $this->assertStringContainsString('Tekan Hubungkan', (string) $session->last_error);

        // Satu rentetan = satu kabar, bukan satu kabar tiap menit.
        $this->assertSame(
            1,
            Notification::where('type', 'session.reconnect_gave_up')->count(),
            'Tiga giliran di atas ambang menghasilkan lebih dari satu kabar.'
        );
    }

    public function test_sesi_yang_berhasil_tersambung_mengembalikan_penghitung_ke_nol(): void
    {
        $session = $this->sesiTerputus(['connect_failures' => WaSession::BATAS_PERCOBAAN_SAMBUNG - 1]);

        Http::fake([
            '*/status' => Http::response([
                'status' => 'connected',
                'phone_number' => '628123456789',
                'push_name' => 'Contoh',
            ], 200),
            '*' => Http::response([], 200),
        ]);

        (new SyncSessionStatusJob)->handle(app(SessionService::class));

        $this->assertSame(0, $session->refresh()->connect_failures);
    }

    public function test_permintaan_manusia_mengembalikan_jatah_penuh(): void
    {
        $this->engineDiam();

        $session = $this->sesiTerputus(['connect_failures' => WaSession::BATAS_PERCOBAAN_SAMBUNG]);

        $this->actingAs($this->pemilik)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('sessions.connect', $session->id));

        $session->refresh();

        // Nol lalu naik lagi jadi 1 pada giliran penjadwal berikutnya — yang
        // penting ia TIDAK lagi berada di ambang.
        $this->assertLessThan(
            WaSession::BATAS_PERCOBAAN_SAMBUNG,
            $session->connect_failures,
            'Menekan Hubungkan tidak mengembalikan jatah percobaan otomatis.'
        );
    }

    public function test_sesi_sehat_tidak_pernah_menyentuh_penghitung(): void
    {
        Http::fake([
            '*/status' => Http::response(['status' => 'connected', 'phone_number' => '628123456789'], 200),
            '*' => Http::response([], 200),
        ]);

        $session = $this->sesiTerputus(['status' => 'connected']);

        for ($i = 0; $i < 5; $i++) {
            (new SyncSessionStatusJob)->handle(app(SessionService::class));
        }

        $this->assertSame(0, $session->refresh()->connect_failures);
    }
}
