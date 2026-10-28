<?php

namespace Tests\Feature;

use App\Http\Controllers\Internal\BootstrapController;
use App\Jobs\SyncSessionStatusJob;
use App\Models\User;
use App\Models\WaSession;
use App\Models\Workspace;
use App\Services\MessageDispatcher;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Menutup jalur otomatis yang membatalkan penegakan langganan.
 *
 * Penegakan lewat tombol sudah lama dijaga: `EnsureSubscriptionActive` menolak
 * POST, `AuthenticateApiKey` menolak API key, `MessageDispatcher` menolak
 * pengiriman. Yang lolos adalah dua jalur yang menjalankan sesi **tanpa ada
 * manusia yang menekan apa pun**, dan keduanya sama sekali tidak memandang
 * status workspace:
 *
 *  A. `SyncSessionStatusJob` berjalan tiap menit dan memanggil `connect()`
 *     untuk sesi yang terbaca `disconnected`. Jadi sesi yang baru dilepas
 *     `releaseSessions()` karena menunggak dijalankan lagi semenit kemudian —
 *     tiap menit, selamanya.
 *
 *  B. `BootstrapController` memberi tahu engine sesi mana yang harus dipulihkan
 *     saat boot. Tiap engine di-deploy ulang, sesi milik pelanggan yang
 *     menunggak ikut hidup lagi.
 *
 * Yang bocor bukan pengiriman pesan — itu tetap ditolak. Yang bocor sumber daya
 * paling langka di platform ini: nomor tetap tertaut dan tetap memakan satu
 * dari tiga slot yang tersedia untuk SELURUH pelanggan.
 */
class PenegakanLanggananTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $nunggak;

    private Workspace $lancar;

    protected function setUp(): void
    {
        parent::setUp();

        $pemilik = User::create([
            'name' => 'Owner',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->nunggak = $this->buatWorkspace($pemilik, 'nunggak');
        $this->nunggak->forceFill([
            'status' => 'suspended',
            'service_until' => now()->subDays(40),
        ])->save();

        $this->lancar = $this->buatWorkspace($pemilik, 'lancar');
        $this->berlangganan($this->lancar, 'prime');
    }

    private function buatWorkspace(User $pemilik, string $slug): Workspace
    {
        $w = Workspace::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'owner_id' => $pemilik->id,
            'owner_email' => $pemilik->email,
        ]);

        $w->members()->attach($pemilik->id, ['role' => 'owner']);

        return $w;
    }

    private function sesi(Workspace $workspace): WaSession
    {
        return $workspace->sessions()->create([
            'name' => 'CS',
            'status' => 'disconnected',
            'driver' => 'wwebjs',
            'auto_reconnect' => true,
            'phone_number' => '6281111111111',
            'connected_at' => now()->subDays(60),
        ]);
    }

    /** Lubang A. */
    public function test_penjadwal_tidak_menjalankan_ulang_sesi_workspace_yang_menunggak(): void
    {
        $this->sesi($this->nunggak);

        $dipanggil = [];
        Http::fake(function ($request) use (&$dipanggil) {
            $dipanggil[] = $request->url();

            return Http::response(['success' => true, 'data' => ['status' => 'disconnected']], 200);
        });

        (new SyncSessionStatusJob)->handle(app(SessionService::class));

        $this->assertEmpty(
            array_filter($dipanggil, fn ($url) => str_contains($url, '/start')),
            'Sesi workspace yang menunggak dijalankan ulang oleh penjadwal.'
        );
    }

    /** Dan sebaliknya — yang membayar tetap dipulihkan otomatis. */
    public function test_penjadwal_tetap_menjalankan_ulang_sesi_yang_membayar(): void
    {
        $this->sesi($this->lancar);

        $dipanggil = [];
        Http::fake(function ($request) use (&$dipanggil) {
            $dipanggil[] = $request->url();

            return Http::response(['success' => true, 'data' => ['status' => 'disconnected']], 200);
        });

        (new SyncSessionStatusJob)->handle(app(SessionService::class));

        $this->assertNotEmpty(
            array_filter($dipanggil, fn ($url) => str_contains($url, '/start')),
            'Sesi pelanggan yang membayar tidak dipulihkan — pemulihan otomatis justru alasan project ini ada.'
        );
    }

    /** Lubang B. */
    public function test_engine_tidak_memulihkan_sesi_workspace_yang_menunggak(): void
    {
        $mati = $this->sesi($this->nunggak);
        $hidup = $this->sesi($this->lancar);

        $data = json_decode(app(BootstrapController::class)()->getContent(), true);
        $ids = collect($data['data'])->pluck('session_id');

        $this->assertFalse($ids->contains($mati->id), 'Sesi workspace menunggak ikut dipulihkan saat engine boot.');
        $this->assertTrue($ids->contains($hidup->id), 'Sesi pelanggan yang membayar tidak ikut dipulihkan.');
    }

    /**
     * Masa berlaku yang lewat tanpa job harian sempat berjalan juga tertutup:
     * `layananHidup()` memeriksa tanggalnya, bukan cuma kolom status.
     */
    public function test_masa_berlaku_lewat_langsung_keluar_dari_pemulihan(): void
    {
        $sesi = $this->sesi($this->lancar);

        // Persis keadaan pukul 00:01: tanggal lewat, status belum sempat diubah.
        $this->lancar->forceFill([
            'status' => 'active',
            'service_until' => now()->subMinute(),
        ])->save();

        $data = json_decode(app(BootstrapController::class)()->getContent(), true);

        $this->assertFalse(collect($data['data'])->pluck('session_id')->contains($sesi->id));
    }

    /**
     * Dan penegakan yang sudah ada tetap berlaku — ditulis di sini supaya satu
     * berkas menjawab pertanyaan "apa saja yang menahan pemakaian tanpa bayar".
     */
    public function test_pengiriman_pesan_tetap_ditolak(): void
    {
        $sesi = $this->sesi($this->nunggak);

        $this->expectException(RuntimeException::class);

        app(MessageDispatcher::class)->queue(
            WaSession::with('workspace')->findOrFail($sesi->id),
            '6282222222222',
            ['body' => 'halo']
        );
    }
}
