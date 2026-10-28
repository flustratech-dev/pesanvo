<?php

namespace Tests\Feature;

use App\Jobs\PushLinkedAccountJob;
use App\Models\User;
use App\Models\Workspace;
use App\Services\LinkedAccounts\LinkedAccountSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as PermintaanKeluar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Akun tertaut dengan aplikasi pesanvo: satu akun, dua aplikasi, hanya
 * autentikasi. Yang dijaga di sini dua arah — perubahan dari seberang
 * diterapkan dengan benar, dan perubahan di sini benar-benar dikirim — plus
 * dua hal yang paling mahal kalau rusak: endpoint yang bisa mengganti kata
 * sandi tanpa tanda tangan, dan lingkaran kirim-balik tanpa akhir.
 */
class AkunTertautTest extends TestCase
{
    use RefreshDatabase;

    private const RAHASIA = 'rahasia-penautan-uji';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.linked_accounts.url' => 'https://pesanvo.test',
            'services.linked_accounts.secret' => self::RAHASIA,
        ]);
    }

    private function kirimMasuk(array $isi, ?string $rahasia = self::RAHASIA, ?int $waktu = null)
    {
        $body = json_encode($isi);
        $waktu = (string) ($waktu ?? now()->getTimestamp());

        return $this->call('POST', '/api/internal/akun-tertaut', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_AKUN_TIMESTAMP' => $waktu,
            'HTTP_X_AKUN_SIGNATURE' => hash_hmac('sha256', $waktu.'.'.$body, (string) $rahasia),
        ], $body);
    }

    private function isi(array $timpa = []): array
    {
        return array_merge([
            'mode' => 'sync',
            'origin' => 'pesanvo',
            'email' => 'budi@contoh.id',
            'previous_email' => null,
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'password_hash' => Hash::make('sandi-rahasia-1'),
            // Terverifikasi di asal: hanya kiriman seperti ini yang boleh mengubah
            // akun yang sudah ada. Kasus belum terverifikasi diuji tersendiri.
            'email_verified_at' => '2026-09-19T10:00:00+07:00',
        ], $timpa);
    }

    // ---------------------------------------------------------------- masuk

    public function test_endpoint_mati_total_kalau_rahasia_kosong(): void
    {
        config(['services.linked_accounts.secret' => null]);

        $this->kirimMasuk($this->isi(), 'apa-saja')->assertStatus(503);
        $this->assertSame(0, User::count());
    }

    public function test_tanda_tangan_salah_atau_kedaluwarsa_ditolak(): void
    {
        $this->kirimMasuk($this->isi(), 'rahasia-salah')->assertStatus(401);
        $this->kirimMasuk($this->isi(), self::RAHASIA, now()->subMinutes(10)->getTimestamp())->assertStatus(401);

        $this->assertSame(0, User::count());
    }

    public function test_akun_baru_dibuat_dan_bisa_masuk_dengan_kata_sandi_yang_sama(): void
    {
        $this->kirimMasuk($this->isi())->assertOk()->assertJson(['action' => 'dibuat']);

        $user = User::where('email', 'budi@contoh.id')->firstOrFail();
        $this->assertSame('Budi Santoso', $user->name);
        $this->assertSame('6281234567890', $user->phone);

        $this->post(route('login'), ['email' => 'budi@contoh.id', 'password' => 'sandi-rahasia-1'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_perubahan_kata_sandi_diterapkan_dan_mode_link_tidak_menimpa(): void
    {
        $this->kirimMasuk($this->isi())->assertOk();

        $this->kirimMasuk($this->isi(['mode' => 'link', 'password_hash' => Hash::make('sandi-lain')]))
            ->assertOk()->assertJson(['action' => 'tidak_berubah']);
        $this->assertTrue(Hash::check('sandi-rahasia-1', User::first()->password));

        $this->kirimMasuk($this->isi(['password_hash' => Hash::make('sandi-baru-2')]))
            ->assertOk()->assertJson(['action' => 'diperbarui']);
        $this->assertTrue(Hash::check('sandi-baru-2', User::first()->password));
    }

    public function test_ganti_email_mengikuti_email_lama_dan_ikut_ke_workspace(): void
    {
        $this->kirimMasuk($this->isi())->assertOk();
        $user = User::first();
        $workspace = Workspace::create([
            'name' => 'Toko Budi',
            'slug' => 'toko-budi',
            'owner_id' => $user->id,
            'owner_email' => $user->email,
        ]);

        $this->kirimMasuk($this->isi(['email' => 'budi.baru@contoh.id', 'previous_email' => 'budi@contoh.id']))
            ->assertOk()->assertJson(['action' => 'diperbarui']);

        $this->assertSame(1, User::count(), 'Ganti email tidak boleh melahirkan akun kedua.');
        $this->assertSame('budi.baru@contoh.id', $user->fresh()->email);
        $this->assertSame('budi.baru@contoh.id', $workspace->fresh()->owner_email);
    }

    public function test_email_baru_yang_sudah_dipakai_orang_lain_ditolak(): void
    {
        $this->kirimMasuk($this->isi())->assertOk();
        $this->kirimMasuk($this->isi(['email' => 'ani@contoh.id']))->assertOk();

        $this->kirimMasuk($this->isi(['email' => 'ani@contoh.id', 'previous_email' => 'budi@contoh.id']))
            ->assertStatus(409);

        $this->assertNotNull(User::where('email', 'budi@contoh.id')->first());
    }

    /**
     * Pengambilalihan akun: seseorang mendaftar di seberang memakai email orang
     * lain (pendaftaran belum tentu memverifikasi email), lalu penautan mengganti
     * kata sandi akun asli pemilik email itu di sini.
     */
    public function test_kiriman_belum_terverifikasi_tidak_mengubah_akun_yang_sudah_ada(): void
    {
        $this->kirimMasuk($this->isi())->assertOk()->assertJson(['action' => 'dibuat']);

        $this->kirimMasuk($this->isi(['email_verified_at' => null, 'password_hash' => Hash::make('sandi-penyerang')]))
            ->assertOk()->assertJson(['action' => 'dilindungi']);

        $this->assertTrue(Hash::check('sandi-rahasia-1', User::first()->password));
    }

    public function test_kiriman_belum_terverifikasi_tetap_membuat_akun_baru(): void
    {
        $this->kirimMasuk($this->isi(['email_verified_at' => null]))->assertOk()->assertJson(['action' => 'dibuat']);

        $this->assertNull(User::first()->email_verified_at);
    }

    public function test_super_admin_tidak_bisa_diubah_dari_seberang(): void
    {
        $admin = User::withoutEvents(fn () => User::create([
            'name' => 'Admin',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('sandi-admin'),
            'is_super_admin' => true,
        ]));

        $this->kirimMasuk($this->isi())->assertOk()->assertJson(['action' => 'dilindungi']);

        $this->assertTrue(Hash::check('sandi-admin', $admin->fresh()->password));
    }

    public function test_kata_sandi_polos_ditolak(): void
    {
        $this->kirimMasuk($this->isi(['password_hash' => 'sandi-polos']))->assertStatus(422);

        $this->assertSame(0, User::count());
    }

    public function test_perubahan_dari_seberang_tidak_dikirim_balik(): void
    {
        Queue::fake();

        $this->kirimMasuk($this->isi())->assertOk();
        $this->kirimMasuk($this->isi(['password_hash' => Hash::make('sandi-baru-2')]))->assertOk();

        Queue::assertNothingPushed();
        $this->assertNull(User::first()->linked_sync_pending_at);
    }

    // ---------------------------------------------------------------- keluar

    public function test_pendaftaran_menandai_akun_dan_mengantrekan_pengiriman(): void
    {
        Queue::fake();

        $this->post(route('register'), [
            'name' => 'Citra',
            'email' => 'citra@contoh.id',
            'workspace' => 'Toko Citra',
            'password' => 'sandi-citra-1',
            'password_confirmation' => 'sandi-citra-1',
            'terms' => '1',
        ])->assertRedirect();

        $user = User::where('email', 'citra@contoh.id')->firstOrFail();
        $this->assertNotNull($user->linked_sync_pending_at);
        Queue::assertPushed(PushLinkedAccountJob::class, fn ($job) => $job->userId === $user->id);
    }

    public function test_penautan_mati_tetap_menandai_tapi_tidak_mengantrekan(): void
    {
        config(['services.linked_accounts.url' => '']);
        Queue::fake();

        $user = User::create(['name' => 'Dodi', 'email' => 'dodi@contoh.id', 'password' => Hash::make('x')]);

        $this->assertNotNull($user->fresh()->linked_sync_pending_at);
        Queue::assertNothingPushed();
    }

    public function test_pengiriman_bertanda_tangan_membawa_hash_dan_melepas_penanda(): void
    {
        Queue::fake();
        Http::fake(['pesanvo.test/*' => Http::response(['success' => true, 'action' => 'dibuat'])]);

        $user = User::create(['name' => 'Eka', 'email' => 'eka@contoh.id', 'password' => Hash::make('sandi-eka')]);

        $hasil = app(LinkedAccountSync::class)->push($user);

        $this->assertSame('terkirim', $hasil['status']);
        $this->assertNull($user->fresh()->linked_sync_pending_at);

        Http::assertSent(function (PermintaanKeluar $r) use ($user) {
            $tanda = hash_hmac('sha256', $r->header('X-Akun-Timestamp')[0].'.'.$r->body(), self::RAHASIA);

            return $r->url() === 'https://pesanvo.test/api/internal/akun-tertaut'
                && hash_equals($tanda, $r->header('X-Akun-Signature')[0])
                && $r['email'] === 'eka@contoh.id'
                && $r['password_hash'] === $user->fresh()->getRawOriginal('password')
                && Hash::check('sandi-eka', $r['password_hash']);
        });
    }

    public function test_seberang_galat_penanda_tetap_dan_ditolak_penanda_dilepas(): void
    {
        Queue::fake();
        $user = User::create(['name' => 'Fani', 'email' => 'fani@contoh.id', 'password' => Hash::make('x')]);

        Http::fake(['*' => Http::sequence()
            ->push('rusak', 500)
            ->push(['error' => ['message' => 'Tanda tangan tidak cocok.']], 401)
            ->push(['error' => ['message' => 'bentrok']], 409)]);

        $this->assertSame('gagal', app(LinkedAccountSync::class)->push($user)['status']);
        $this->assertNotNull($user->fresh()->linked_sync_pending_at, 'Galat sementara harus diulang penjadwal.');

        // Rahasia yang belum disamakan bukan alasan membuang perubahan yang menunggu.
        $this->assertSame('gagal', app(LinkedAccountSync::class)->push($user)['status']);
        $this->assertNotNull($user->fresh()->linked_sync_pending_at);

        $this->assertSame('ditolak', app(LinkedAccountSync::class)->push($user)['status']);
        $this->assertNull($user->fresh()->linked_sync_pending_at, 'Penolakan tidak akan berubah kalau diulang.');
    }

    public function test_penautan_awal_tidak_melepas_perubahan_kata_sandi_yang_tertunda(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response(['success' => true, 'action' => 'tidak_berubah'])]);
        $user = User::create(['name' => 'Gita', 'email' => 'gita@contoh.id', 'password' => Hash::make('x')]);

        app(LinkedAccountSync::class)->push($user, LinkedAccountSync::MODE_LINK);

        $this->assertNotNull($user->fresh()->linked_sync_pending_at);
    }

    public function test_ganti_kata_sandi_dan_email_ditandai_beserta_email_lama(): void
    {
        Queue::fake();
        $user = User::create(['name' => 'Hadi', 'email' => 'hadi@contoh.id', 'password' => Hash::make('x')]);
        DB::table('users')->where('id', $user->id)->update(['linked_sync_pending_at' => null]);

        $user->refresh()->forceFill(['email' => 'hadi.baru@contoh.id'])->save();

        $this->assertNotNull($user->fresh()->linked_sync_pending_at);
        $this->assertSame('hadi@contoh.id', $user->fresh()->linked_sync_previous_email);

        // Profil biasa (nama) bukan identitas masuk — tidak dikirim.
        DB::table('users')->where('id', $user->id)->update(['linked_sync_pending_at' => null]);
        $user->refresh()->update(['name' => 'Hadi Baru']);
        $this->assertNull($user->fresh()->linked_sync_pending_at);
    }

    public function test_perintah_kirim_mengulang_yang_tertunda(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response(['success' => true, 'action' => 'diperbarui'])]);
        User::create(['name' => 'Indah', 'email' => 'indah@contoh.id', 'password' => Hash::make('x')]);

        $this->artisan('akun-tertaut:kirim')->assertSuccessful();

        $this->assertSame(0, User::whereNotNull('linked_sync_pending_at')->count());
        Http::assertSentCount(1);
    }
}
