<?php

namespace Tests\Feature;

use App\Models\Workspace;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Sesi harus pulih sendiri setelah engine di-deploy ulang.
 *
 * Inilah alasan utama gateway ini dibuat, dan justru inilah yang dulu rusak:
 * selama engine mati, penyelaras status memanggil connect(), gagal karena
 * engine tidak terjangkau, lalu menandai sesi `failed`. Status itu membuat
 * sesi dikeluarkan dari daftar pemulihan otomatis — nomor yang sebenarnya
 * masih tertaut tampak putus dan seolah harus di-scan ulang tiap deploy.
 */
class SessionRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'Contoh', 'slug' => 'contoh', 'max_sessions' => 2]);
    }

    public function test_engine_tak_terjangkau_tidak_menandai_sesi_gagal(): void
    {
        $session = $this->workspace->sessions()->create([
            'name' => 'CS',
            'driver' => 'wwebjs',
            'status' => 'connected',
            'connected_at' => now(),
        ]);

        Http::fake(function (): void {
            throw new ConnectionException('Connection refused');
        });

        try {
            app(SessionService::class)->connect($session);
        } catch (ConnectionException) {
            // Pemanggil tetap perlu tahu; yang diuji di sini status akhirnya.
        }

        $this->assertSame('disconnected', $session->fresh()->status);
    }

    public function test_kegagalan_sungguhan_dari_engine_tetap_menandai_gagal(): void
    {
        $session = $this->workspace->sessions()->create(['name' => 'CS', 'driver' => 'wwebjs', 'status' => 'pending']);

        Http::fake(['*' => Http::response(['error' => 'Batas sesi engine tercapai.'], 500)]);

        try {
            app(SessionService::class)->connect($session);
        } catch (\Throwable) {
        }

        $this->assertSame('failed', $session->fresh()->status);
    }

    public function test_sesi_yang_pernah_tersambung_ikut_dipulihkan_walau_berstatus_gagal(): void
    {
        $pernah = $this->workspace->sessions()->create([
            'name' => 'Pernah tersambung',
            'driver' => 'wwebjs',
            'status' => 'failed',
            'connected_at' => now()->subHour(),
        ]);

        $belum = $this->workspace->sessions()->create([
            'name' => 'Belum pernah',
            'driver' => 'wwebjs',
            'status' => 'failed',
        ]);

        $ids = collect($this->bootstrap()->json('data'))->pluck('session_id');

        $this->assertContains($pernah->id, $ids);

        // Sesi yang belum pernah tersambung tidak punya kredensial untuk
        // dipulihkan — menjalankannya hanya memunculkan QR yang tidak ada
        // yang men-scan, dan memakan satu slot di engine.
        $this->assertNotContains($belum->id, $ids);
    }

    public function test_sesi_dengan_auto_reconnect_mati_tidak_dipulihkan(): void
    {
        $session = $this->workspace->sessions()->create([
            'name' => 'Sengaja dimatikan',
            'driver' => 'wwebjs',
            'status' => 'disconnected',
            'connected_at' => now()->subHour(),
            'auto_reconnect' => false,
        ]);

        $ids = collect($this->bootstrap()->json('data'))->pluck('session_id');

        $this->assertNotContains($session->id, $ids);
    }

    /**
     * Body dikirim mentah sebagai string kosong, bukan lewat getJson(): yang
     * ditandatangani engine adalah isi body apa adanya, dan helper JSON milik
     * test menaruh isi yang tidak sama persis sehingga tanda tangannya meleset.
     */
    private function bootstrap()
    {
        $timestamp = (string) now()->getTimestamp();

        return $this->call('GET', '/internal/engine/bootstrap', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_ENGINE_TIMESTAMP' => $timestamp,
            'HTTP_X_ENGINE_SIGNATURE' => hash_hmac('sha256', $timestamp.'.', config('gateway.engine.hmac_secret')),
        ], '');
    }
}
