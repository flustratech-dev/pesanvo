<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Autentikasi dua faktor.
 *
 * Sengaja memakai `be()` dan BUKAN `actingAs()`: `TestCase::actingAs` sudah
 * melewatkan faktor kedua supaya puluhan tes lain tidak terpental ke /2fa.
 * Di berkas ini yang sedang diuji justru penegakannya apa adanya, jadi jalan
 * pintas itu harus dilewati.
 */
class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
    }

    private function pengguna(bool $admin = false): User
    {
        return User::create([
            'name' => $admin ? 'Admin' : 'Pelanggan',
            'email' => $admin ? 'admin-2fa@pesanvo.id' : 'orang-2fa@contoh.id',
            'password' => Hash::make('rahasia12345'),
            'is_super_admin' => $admin,
        ]);
    }

    // ------------------------------------------------------------- algoritma

    /**
     * Vektor uji resmi RFC 6238.
     *
     * Ini yang membenarkan menulis TOTP sendiri alih-alih memakai paket: yang
     * perlu dijaga cuma kesesuaiannya dengan RFC, dan kesesuaian itu bisa
     * dibuktikan angka demi angka. Rahasianya "12345678901234567890" dalam ASCII.
     */
    public function test_kode_cocok_dengan_vektor_uji_rfc_6238(): void
    {
        $rahasia = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        $vektor = [
            59 => '287082',
            1111111109 => '081804',
            1111111111 => '050471',
            1234567890 => '005924',
            2000000000 => '279037',
        ];

        foreach ($vektor as $waktu => $harapan) {
            $this->assertSame($harapan, Totp::kode($rahasia, intdiv($waktu, 30)), "Kode untuk t={$waktu} tidak cocok.");
        }
    }

    /**
     * Toleransi satu jendela ke depan dan ke belakang.
     *
     * Bukan kelonggaran berlebihan melainkan syarat agar fitur ini bisa dipakai:
     * jam ponsel dan jam server hampir tidak pernah sama persis, dan tanpa
     * toleransi selisih beberapa detik saja membuat setiap kode ditolak. Yang
     * mengalaminya menyimpulkan 2FA-nya rusak, bukan jamnya yang meleset.
     */
    public function test_kode_dari_jendela_sebelumnya_masih_diterima(): void
    {
        $rahasia = Totp::rahasiaBaru();
        $sekarang = intdiv(time(), 30);

        $this->assertTrue(Totp::sah($rahasia, Totp::kode($rahasia, $sekarang - 1)));
        $this->assertTrue(Totp::sah($rahasia, Totp::kode($rahasia, $sekarang + 1)));
        $this->assertFalse(Totp::sah($rahasia, Totp::kode($rahasia, $sekarang - 5)));
    }

    public function test_toleransi_lebih_longgar_bila_ditentukan(): void
    {
        $rahasia = Totp::rahasiaBaru();
        $sekarang = intdiv(time(), 30);

        $this->assertTrue(Totp::sah($rahasia, Totp::kode($rahasia, $sekarang - 12), toleransi: 20));
    }

    // ------------------------------------------------------------ penegakan

    /**
     * Secara bawaan, 2FA opsional untuk seluruh peran termasuk super admin:
     * Super admin baru bisa masuk dashboard dan panel admin tanpa dialihkan ke two-factor.setup.
     */
    public function test_super_admin_baru_bisa_masuk_dashboard_dan_panel_admin_tanpa_dua_faktor(): void
    {
        $admin = $this->pengguna(admin: true);
        $ws = Workspace::create([
            'name' => 'Workspace Admin',
            'slug' => 'workspace-admin',
            'owner_id' => $admin->id,
            'owner_email' => $admin->email,
            'max_sessions' => 1,
            'monthly_message_quota' => 100,
        ]);
        $ws->members()->attach($admin->id, ['role' => 'owner']);

        $this->be($admin)->withSession(['current_workspace_id' => $ws->id]);

        $this->get(route('admin.overview'))->assertOk();
        $this->get(route('dashboard'))->assertOk();
    }

    /**
     * Jaring pengaman: dengan konfigurasi dinyalakan, super admin kembali
     * dipaksa memasang 2FA sebelum bisa mengakses dashboard dan panel admin.
     */
    public function test_admin_tanpa_dua_faktor_dipaksa_memasangnya_bila_konfigurasi_aktif(): void
    {
        config(['auth.two_factor_mandatory_for_admin' => true]);

        $this->be($this->pengguna(admin: true));

        $this->get(route('admin.overview'))->assertRedirect(route('two-factor.setup'));
        $this->get(route('dashboard'))->assertRedirect(route('two-factor.setup'));
    }

    /**
     * Super admin yang 2FA-nya aktif tetap ditantang kode saat login
     * (cabang duaFaktorAktif di EnsureTwoFactor tetap berlaku).
     */
    public function test_super_admin_dengan_dua_faktor_aktif_tetap_ditantang_kode_saat_login(): void
    {
        $admin = $this->pengguna(admin: true);
        $rahasia = Totp::rahasiaBaru();

        $admin->forceFill([
            'two_factor_secret' => $rahasia,
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->be($admin);

        // Belum melewati tantangan: dialihkan ke two-factor.challenge
        $this->get(route('admin.overview'))->assertRedirect(route('two-factor.challenge'));
        $this->get(route('dashboard'))->assertRedirect(route('two-factor.challenge'));

        // Menjawab tantangan dengan kode yang benar
        $this->post(route('two-factor.verify'), ['kode' => Totp::kode($rahasia)])
            ->assertRedirect();

        $this->assertTrue(session('2fa.lolos'));
    }

    /** Pelanggan biasa tidak dipaksa apa pun. */
    public function test_pelanggan_tidak_dipaksa_memasang_dua_faktor(): void
    {
        $this->be($this->pengguna());

        $this->get(route('two-factor.setup'))->assertOk();
        $this->get(route('profile.show'))->assertRedirect(route('onboarding.create'));
    }

    /**
     * Pendaftar biasa lewat alur register sungguhan tidak pernah dipaksa 2FA:
     * - Mendarat di tujuan register (sessions.index), bukan di two-factor.setup
     * - Tidak ada pengalihan 2FA di permintaan berikutnya ke dashboard
     * - is_super_admin bernilai false
     */
    public function test_pendaftar_biasa_lewat_alur_register_tidak_dipaksa_dua_faktor(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Pendaftar Biasa',
            'email' => 'pendaftar.biasa@contoh.id',
            'workspace' => 'Toko Biasa',
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
            'terms' => '1',
        ]);

        $user = User::where('email', 'pendaftar.biasa@contoh.id')->firstOrFail();

        // 1. Pengguna mendarat di halaman tujuan register, BUKAN di two-factor.setup
        $response->assertRedirect(route('sessions.index'));
        $this->assertNotSame(route('two-factor.setup'), $response->headers->get('Location'));

        // 2. is_super_admin bernilai false
        $this->assertFalse($user->is_super_admin);
        $this->assertFalse($user->wajibDuaFaktor());
        $this->assertFalse($user->duaFaktorAktif());

        // 3. Tidak ada pengalihan 2FA di permintaan berikutnya ke dashboard
        $dashboardResponse = $this->get(route('dashboard'));
        $dashboardResponse->assertOk();
        $this->assertFalse($dashboardResponse->isRedirect());
    }

    /**
     * Penegakan berlaku di SELURUH halaman web, bukan cuma panel admin.
     *
     * Kalau hanya panel admin yang dijaga, kata sandi admin yang bocor tetap
     * bisa dipakai membuka dashboard, mengekspor data, dan membuat API key —
     * seluruhnya di luar panel, seluruhnya tanpa faktor kedua.
     */
    public function test_sesi_yang_belum_menjawab_tantangan_ditahan(): void
    {
        $user = $this->pengguna();
        $rahasia = Totp::rahasiaBaru();

        $user->forceFill([
            'two_factor_secret' => $rahasia,
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->be($user);

        $this->get(route('profile.show'))->assertRedirect(route('two-factor.challenge'));
        $this->get(route('dashboard'))->assertRedirect(route('two-factor.challenge'));

        $this->post(route('two-factor.verify'), ['kode' => Totp::kode($rahasia)])
            ->assertRedirect();

        $this->assertTrue(session('2fa.lolos'));
    }

    public function test_kode_salah_ditolak_dan_sesi_tetap_tertahan(): void
    {
        $user = $this->pengguna();

        $user->forceFill([
            'two_factor_secret' => Totp::rahasiaBaru(),
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->be($user);

        $this->post(route('two-factor.verify'), ['kode' => '000000'])
            ->assertSessionHasErrors('kode');

        $this->assertNull(session('2fa.lolos'));
    }

    // ------------------------------------------------------------ pemasangan

    /** Halaman Profil benar-benar merender bagian 2FA-nya. */
    public function test_halaman_profil_menampilkan_bagian_dua_faktor(): void
    {
        $user = $this->pengguna();

        $ws = Workspace::create([
            'name' => 'Punya Saya',
            'slug' => 'punya-saya',
            'owner_id' => $user->id,
            'owner_email' => $user->email,
            'max_sessions' => 1,
            'monthly_message_quota' => 100,
        ]);
        $ws->members()->attach($user->id, ['role' => 'owner']);
        $this->berlangganan($ws);

        $this->be($user)
            ->withSession(['current_workspace_id' => $ws->id])
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Autentikasi dua faktor')
            ->assertSee('Nyalakan 2FA');
    }

    /**
     * Rahasia tidak ditulis ke akun sampai kode pertamanya cocok.
     *
     * Kalau ia langsung disimpan, orang yang membuka halaman pemasangan lalu
     * menutup tabnya akan punya rahasia yang tidak ada di aplikasi mana pun —
     * dan sejak itu diminta kode yang tidak bisa ia dapatkan dari mana pun.
     * Terkunci di luar akunnya sendiri oleh fitur yang belum sempat dinyalakan.
     */
    public function test_membuka_halaman_pemasangan_belum_menyalakan_apa_pun(): void
    {
        $user = $this->pengguna();

        $this->be($user)->get(route('two-factor.setup'))->assertOk();

        $this->assertFalse($user->refresh()->duaFaktorAktif());
        $this->assertNull($user->two_factor_secret);
    }

    public function test_kode_benar_menyalakan_dan_memberi_kode_pemulihan(): void
    {
        $user = $this->pengguna();

        $this->be($user)->get(route('two-factor.setup'));
        $rahasia = session('2fa.rahasia');

        $this->post(route('two-factor.enable'), ['kode' => Totp::kode($rahasia)])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('kodePemulihan');

        $user->refresh();

        $this->assertTrue($user->duaFaktorAktif());
        $this->assertCount(8, $user->two_factor_recovery_codes);
    }

    public function test_halaman_profil_menampilkan_tombol_unduh_csv_kode_pemulihan(): void
    {
        $user = $this->pengguna();
        $ws = Workspace::create([
            'name' => 'Ruang Uji',
            'slug' => 'ruang-uji',
            'owner_id' => $user->id,
            'owner_email' => $user->email,
            'max_sessions' => 1,
            'monthly_message_quota' => 100,
        ]);
        $ws->members()->attach($user->id, ['role' => 'owner']);
        $this->berlangganan($ws);

        $kodePemulihan = ['kode1-aaaaa', 'kode2-bbbbb', 'kode3-ccccc', 'kode4-ddddd', 'kode5-eeeee', 'kode6-fffff', 'kode7-ggggg', 'kode8-hhhhh'];

        $this->be($user)
            ->withSession([
                'current_workspace_id' => $ws->id,
                'kodePemulihan' => $kodePemulihan,
            ])
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Simpan 8 kode pemulihan ini sekarang')
            ->assertSee('Unduh CSV')
            ->assertSee('Unduh TXT')
            ->assertSee('Salin Semua')
            ->assertSee('kode1-aaaaa');
    }

    public function test_unduh_kode_pemulihan_csv_berhasil(): void
    {
        $user = $this->pengguna();
        $user->forceFill([
            'two_factor_secret' => Totp::rahasiaBaru(),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['code-11111', 'code-22222'],
        ])->save();

        $response = $this->be($user)
            ->withSession(['2fa.lolos' => true])
            ->get(route('two-factor.recovery-codes.csv'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8')
            ->assertSee('No,Kode Pemulihan')
            ->assertSee('code-11111')
            ->assertSee('code-22222');
    }

    public function test_tampilkan_ulang_kode_pemulihan_dengan_kata_sandi(): void
    {
        $user = $this->pengguna();
        $user->forceFill([
            'two_factor_secret' => Totp::rahasiaBaru(),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['code-11111', 'code-22222'],
        ])->save();

        $this->be($user)
            ->withSession(['2fa.lolos' => true])
            ->post(route('two-factor.recovery-codes.show'), ['password' => 'rahasia12345'])
            ->assertRedirect()
            ->assertSessionHas('kodePemulihan', ['code-11111', 'code-22222']);
    }

    public function test_buat_ulang_kode_pemulihan_menerbitkan_8_kode_baru_dan_menghapus_yang_lama(): void
    {
        $user = $this->pengguna();
        $user->forceFill([
            'two_factor_secret' => Totp::rahasiaBaru(),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['code-lama-1', 'code-lama-2'],
        ])->save();

        $response = $this->be($user)
            ->withSession(['2fa.lolos' => true])
            ->post(route('two-factor.recovery-codes.regenerate'), ['password' => 'rahasia12345']);

        $response->assertRedirect();
        $response->assertSessionHas('kodePemulihan');
        $response->assertSessionHas('swal');

        $user->refresh();
        $this->assertCount(8, $user->two_factor_recovery_codes);
        $this->assertNotContains('code-lama-1', $user->two_factor_recovery_codes);
        $this->assertNotContains('code-lama-2', $user->two_factor_recovery_codes);
    }

    public function test_kode_salah_tidak_menyalakan_apa_pun(): void
    {
        $user = $this->pengguna();

        $this->be($user)->get(route('two-factor.setup'));

        $this->post(route('two-factor.enable'), ['kode' => '000000'])
            ->assertSessionHasErrors('kode');

        $this->assertFalse($user->refresh()->duaFaktorAktif());
    }

    // -------------------------------------------------------- kode pemulihan

    /** Kode pemulihan dibuang setelah dipakai, bukan sekadar ditandai. */
    public function test_kode_pemulihan_hanya_berlaku_sekali(): void
    {
        $user = $this->pengguna();

        $user->forceFill([
            'two_factor_secret' => Totp::rahasiaBaru(),
            'two_factor_recovery_codes' => ['aaaaa-bbbbb', 'ccccc-ddddd'],
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->be($user);

        $this->post(route('two-factor.verify'), ['kode' => 'aaaaa-bbbbb'])->assertRedirect();
        $this->assertTrue(session('2fa.lolos'));
        $this->assertSame(['ccccc-ddddd'], $user->refresh()->two_factor_recovery_codes);

        // Sesi baru, kode yang sama: harus ditolak.
        $this->flushSession();
        $this->be($user->refresh());

        $this->post(route('two-factor.verify'), ['kode' => 'aaaaa-bbbbb'])
            ->assertSessionHasErrors('kode');
    }

    // ---------------------------------------------------------- mematikan

    public function test_mematikan_butuh_kata_sandi(): void
    {
        $user = $this->pengguna();

        $user->forceFill([
            'two_factor_secret' => Totp::rahasiaBaru(),
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->be($user)->withSession(['2fa.lolos' => true]);

        $this->delete(route('two-factor.disable'), ['password' => 'salah'])
            ->assertSessionHasErrors('password');

        $this->assertTrue($user->refresh()->duaFaktorAktif());

        $this->delete(route('two-factor.disable'), ['password' => 'rahasia12345'])
            ->assertRedirect();

        $this->assertFalse($user->refresh()->duaFaktorAktif());
    }

    /**
     * Super admin bisa mematikan 2FA-nya sendiri dari halaman profil bila 2FA opsional (bawaan).
     */
    public function test_super_admin_bisa_mematikan_dua_faktor_dari_profil(): void
    {
        $admin = $this->pengguna(admin: true);
        $ws = Workspace::create([
            'name' => 'Workspace Admin',
            'slug' => 'workspace-admin',
            'owner_id' => $admin->id,
            'owner_email' => $admin->email,
            'max_sessions' => 1,
            'monthly_message_quota' => 100,
        ]);
        $ws->members()->attach($admin->id, ['role' => 'owner']);
        $this->berlangganan($ws);

        $admin->forceFill([
            'two_factor_secret' => Totp::rahasiaBaru(),
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->be($admin)->withSession([
            'current_workspace_id' => $ws->id,
            '2fa.lolos' => true,
        ]);

        // Form matikan 2FA tampil di halaman profil
        $this->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Matikan 2FA');

        // Mematikan dengan kata sandi yang valid
        $this->delete(route('two-factor.disable'), ['password' => 'rahasia12345'])
            ->assertRedirect();

        $this->assertFalse($admin->refresh()->duaFaktorAktif());
    }

    /**
     * Super admin dan pengguna biasa dapat mematikan 2FA dari profil setting dengan kata sandi.
     */
    public function test_admin_bisa_mematikan_dua_faktor_dari_profil_dengan_kata_sandi(): void
    {
        $admin = $this->pengguna(admin: true);

        $admin->forceFill([
            'two_factor_secret' => Totp::rahasiaBaru(),
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->be($admin)->withSession(['2fa.lolos' => true]);

        $this->delete(route('two-factor.disable'), ['password' => 'rahasia12345'])
            ->assertRedirect();

        $this->assertFalse($admin->refresh()->duaFaktorAktif());
    }

    /**
     * Rahasia TOTP tersimpan terenkripsi, tidak sebagai teks polos.
     *
     * Ia harus bisa dibaca kembali untuk menghitung kode, jadi hash mustahil.
     * Yang bisa dilakukan adalah membuatnya tidak berguna bagi siapa pun yang
     * mendapat salinan basis data tanpa APP_KEY — mencakup dump SQL yang bocor,
     * cadangan, dan akses baca ke replika.
     */
    public function test_rahasia_tidak_tersimpan_sebagai_teks_polos(): void
    {
        $user = $this->pengguna();
        $rahasia = Totp::rahasiaBaru();

        $user->forceFill([
            'two_factor_secret' => $rahasia,
            'two_factor_recovery_codes' => ['aaaaa-bbbbb'],
            'two_factor_confirmed_at' => now(),
        ])->save();

        $mentah = DB::table('users')->where('id', $user->id)->first();

        $this->assertNotSame($rahasia, $mentah->two_factor_secret);
        $this->assertStringNotContainsString($rahasia, (string) $mentah->two_factor_secret);
        $this->assertStringNotContainsString('aaaaa-bbbbb', (string) $mentah->two_factor_recovery_codes);

        // Tetap bisa dibaca kembali oleh aplikasi.
        $this->assertSame($rahasia, $user->refresh()->two_factor_secret);
    }

    /** Rahasia tidak boleh ikut terbawa saat model di-serialize. */
    public function test_rahasia_tidak_ikut_saat_model_diubah_ke_array(): void
    {
        $user = $this->pengguna();

        $user->forceFill([
            'two_factor_secret' => Totp::rahasiaBaru(),
            'two_factor_recovery_codes' => ['aaaaa-bbbbb'],
            'two_factor_confirmed_at' => now(),
        ])->save();

        $array = $user->refresh()->toArray();

        $this->assertArrayNotHasKey('two_factor_secret', $array);
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $array);
    }
}
