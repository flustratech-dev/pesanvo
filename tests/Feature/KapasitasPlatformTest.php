<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SessionService;
use App\Support\KapasitasPlatform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * Batas kapasitas platform harus menggigit SEBELUM pelanggan membayar.
 *
 * Sampai sekarang satu-satunya yang tahu soal `WA_MAX_SESSIONS` adalah engine,
 * dan ia baru menjawabnya saat tombol Hubungkan ditekan — setelah pelanggan
 * mendaftar, memilih paket, mentransfer, dan menunggu buktinya diperiksa
 * manusia. Seluruh langkah itu berhasil; hanya yang terakhir yang gagal, dengan
 * alasan yang sepenuhnya urusan kami.
 *
 * Ada dua ukuran di kelas yang diuji ini, dan menyamakannya sudah pernah salah:
 *
 *   KOMITMEN (`terpakai`) — slot yang sudah dijanjikan kepada langganan
 *   BERBAYAR. Dipakai sebelum menerima uang.
 *
 *   SESI NYATA (`sesiAda`) — nomor yang akan benar-benar minta Chromium.
 *   Dipakai sebelum membuat sesi.
 */
class KapasitasPlatformTest extends TestCase
{
    use RefreshDatabase;

    private User $pengguna;

    protected function setUp(): void
    {
        parent::setUp();

        config(['gateway.engine.max_sessions' => 3]);

        $this->pengguna = User::create([
            'name' => 'Owner',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);
    }

    /**
     * Tiap workspace diberi pemiliknya sendiri kecuali diminta lain.
     *
     * Ini bukan kerapian: `EnsureWorkspaceSelected` memilih workspace pertama
     * milik pengguna kalau pilihannya tidak jelas, jadi satu pengguna yang
     * memiliki empat workspace membuat uji di bawah menembak workspace yang
     * salah — lalu lulus atau gagal karena alasan yang tidak ada hubungannya
     * dengan yang sedang diuji.
     */
    private function workspace(string $slug, int $slot, ?User $pemilik = null): Workspace
    {
        $pemilik ??= User::create([
            'name' => $slug,
            'email' => $slug.'@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $ws = Workspace::create([
            'name' => $slug,
            'slug' => $slug,
            'owner_id' => $pemilik->id,
            'owner_email' => $pemilik->email,
            'max_sessions' => $slot,
            'status' => 'active',
        ]);

        $ws->members()->attach($pemilik->id, ['role' => 'owner']);

        return $ws;
    }

    /** Workspace dengan langganan yang benar-benar sudah dibayar. */
    private function berbayar(string $slug, int $slot, ?User $pemilik = null): Workspace
    {
        $ws = $this->workspace($slug, $slot, $pemilik);

        Subscription::create([
            'workspace_id' => $ws->id,
            'plan_slug' => 'essentials',
            'status' => 'active',
            'period' => 'monthly',
            'current_period_end' => now()->addMonth(),
        ]);

        return $ws;
    }

    public function test_komitmen_dihitung_dari_paket_bukan_dari_sesi_yang_menyala(): void
    {
        $this->berbayar('a', 1);
        $this->berbayar('b', 2);

        // Nol sesi menyala, tapi tiga slot sudah dijanjikan kepada orang yang
        // membayarnya. Menghitung yang menyala berarti menjual slot yang sama
        // dua kali kepada dua orang yang sama-sama sudah membayar.
        $this->assertSame(3, KapasitasPlatform::terpakai());
        $this->assertSame(0, KapasitasPlatform::tersisa());
        $this->assertFalse(KapasitasPlatform::sanggup(1));
    }

    /**
     * Kapasitas yang habis oleh orang yang belum pernah membayar bukan
     * kapasitas yang terjaga — itu kapasitas yang hilang, dan yang tertahan di
     * luar justru pelanggan yang mau membayar.
     */
    public function test_paket_coba_gratis_tidak_menahan_komitmen(): void
    {
        $this->berbayar('bayar', 1);

        $this->workspace('coba1', 1);
        $this->workspace('coba2', 1);
        $this->workspace('coba3', 1);

        $this->assertSame(1, KapasitasPlatform::terpakai());
        $this->assertTrue(KapasitasPlatform::sanggup(2));
    }

    public function test_workspace_yang_layanannya_mati_tidak_menahan_slot(): void
    {
        $this->berbayar('a', 1);

        $mati = $this->berbayar('mati', 2);
        $mati->forceFill(['status' => 'suspended'])->save();

        $this->assertSame(1, KapasitasPlatform::terpakai());
        $this->assertTrue(KapasitasPlatform::sanggup(2));
    }

    public function test_pelanggan_keempat_ditolak_di_checkout_bukan_di_tombol_hubungkan(): void
    {
        $this->berbayar('a', 1);
        $this->berbayar('b', 1);
        $this->berbayar('c', 1);

        // Pendaftar baru: masih di paket coba, belum membayar apa pun.
        $baru = $this->workspace('d', 1, $this->pengguna);

        $this->actingAs($this->pengguna)
            ->withSession(['current_workspace_id' => $baru->id])
            ->post(route('billing.checkout'), ['plan' => 'essentials', 'period' => 'monthly'])
            ->assertSessionHasErrors('plan');

        $this->assertSame(
            0,
            $baru->invoices()->count(),
            'Tagihan terbit padahal kapasitasnya tidak ada. Pelanggan akan mentransfer '
                .'untuk layanan yang tidak bisa kami berikan.'
        );
    }

    public function test_perpanjangan_pelanggan_lama_tidak_ikut_terhalang(): void
    {
        $this->berbayar('a', 1);
        $this->berbayar('b', 1);
        $lama = $this->berbayar('c', 1, $this->pengguna);

        // Slotnya sudah terhitung sebagai miliknya; memperpanjang paket yang
        // sama tidak menambah komitmen apa pun.
        $this->actingAs($this->pengguna)
            ->withSession(['current_workspace_id' => $lama->id])
            ->post(route('billing.checkout'), ['plan' => 'essentials', 'period' => 'monthly'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $lama->invoices()->count());
    }

    public function test_sesi_tidak_bisa_dibuat_kalau_seluruh_slot_engine_sudah_terpakai(): void
    {
        $sessions = app(SessionService::class);

        foreach (['a', 'b', 'c'] as $slug) {
            $ws = $this->berbayar($slug, 1);
            $sessions->create($ws, 'CS')->forceFill(['status' => 'connected'])->save();
        }

        $keempat = $this->workspace('d', 1);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Kapasitas nomor aktif sedang penuh');

        $sessions->create($keempat, 'CS');
    }

    /**
     * Jatah paket sendiri tidak boleh dihalangi oleh batas platform. Ini pernah
     * salah: penjaga pembuatan sesi sempat memakai hitungan KOMITMEN, yang sudah
     * termasuk workspace itu sendiri — jadi pelanggan Elite ditolak membuat sesi
     * keduanya oleh slot yang ia bayar sendiri.
     */
    public function test_pelanggan_elite_tetap_bisa_memakai_kedua_slotnya(): void
    {
        $elite = $this->berbayar('elite', 2);

        $sessions = app(SessionService::class);

        $sessions->create($elite, 'CS')->forceFill(['status' => 'connected'])->save();

        $this->assertNotNull($sessions->create($elite, 'Penjualan')->id);
    }

    public function test_workspace_bebas_tidak_tunduk_pada_batas_platform(): void
    {
        $sessions = app(SessionService::class);

        foreach (['a', 'b', 'c'] as $slug) {
            $ws = $this->berbayar($slug, 1);
            $sessions->create($ws, 'CS')->forceFill(['status' => 'connected'])->save();
        }

        $internal = $this->workspace('pesanvo', 1);
        $internal->forceFill(['is_internal' => true])->save();

        // Nomor kami sendiri mengirim pemberitahuan penagihan ke seluruh
        // pelanggan. Kalau ia ikut tertahan batas yang sama, kapasitas penuh
        // membuat kami kehilangan kemampuan mengabari siapa pun soal itu.
        $this->assertNotNull($sessions->create($internal, 'Notifikasi')->id);
    }
}
