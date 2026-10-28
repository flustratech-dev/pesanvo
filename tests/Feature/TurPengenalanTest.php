<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGuideProgress;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Tur pengenalan: sekali saja, dan hanya untuk pendaftar baru.
 *
 * Dua cara ia gagal, dan keduanya dijaga di sini. Muncul lagi setelah ditutup
 * terbaca sebagai kerusakan — orang berhenti mempercayai antarmuka yang tampak
 * tidak mengingat apa pun. Dan muncul untuk pelanggan lama berarti menuntun
 * mereka melewati hal yang sudah mereka kerjakan tiap hari.
 */
class TurPengenalanTest extends TestCase
{
    use RefreshDatabase;

    private const KUNCI = 'dashboard.mulai';

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        Mail::fake();
    }

    private function daftar(string $email = 'baru@contoh.id'): User
    {
        $this->post('/register', [
            'name' => 'Pendaftar Baru',
            'email' => $email,
            'workspace' => 'Toko Baru',
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
            'terms' => '1',
        ]);

        return User::where('email', $email)->firstOrFail();
    }

    public function test_pendaftar_baru_melihat_tur_di_dashboard(): void
    {
        $this->daftar();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-tur-pengenalan', false)
            ->assertSee('Selamat datang di Pesanvo');
    }

    /**
     * Inti dari fitur ini: sekali saja.
     *
     * Tur yang kembali muncul setelah ditutup terbaca sebagai kerusakan.
     */
    public function test_tur_tidak_muncul_lagi_setelah_dicatat(): void
    {
        $user = $this->daftar();

        $this->postJson(route('onboarding.guides.ack', ['guideKey' => self::KUNCI]), [
            'status' => 'completed',
        ])->assertOk();

        $this->assertTrue($user->fresh()->hasSeenGuide(self::KUNCI));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('data-tur-pengenalan', false);
    }

    /** Dilewati sama dengan selesai untuk urusan "jangan tampilkan lagi". */
    public function test_tur_yang_dilewati_juga_tidak_muncul_lagi(): void
    {
        $this->daftar();

        $this->postJson(route('onboarding.guides.ack', ['guideKey' => self::KUNCI]), [
            'status' => 'skipped',
        ])->assertOk();

        // Tapi dicatat APA ADANYA: berapa banyak orang yang melewatinya adalah
        // satu-satunya tanda bahwa turnya sendiri yang bermasalah.
        $this->assertSame('skipped', UserGuideProgress::firstOrFail()->status);

        $this->get(route('dashboard'))->assertDontSee('data-tur-pengenalan', false);
    }

    public function test_status_selain_selesai_dan_dilewati_ditolak(): void
    {
        $this->daftar();

        $this->postJson(route('onboarding.guides.ack', ['guideKey' => self::KUNCI]), [
            'status' => 'entah-apa',
        ])->assertStatus(422);

        $this->assertSame(0, UserGuideProgress::count());
    }

    public function test_mencatat_dua_kali_tidak_menghasilkan_dua_baris(): void
    {
        $this->daftar();

        foreach (range(1, 3) as $ke) {
            $this->postJson(route('onboarding.guides.ack', ['guideKey' => self::KUNCI]), [
                'status' => 'completed',
            ])->assertOk();
        }

        $this->assertSame(1, UserGuideProgress::count());
    }

    public function test_tamu_tidak_bisa_mencatat_tur(): void
    {
        $this->postJson(route('onboarding.guides.ack', ['guideKey' => self::KUNCI]), [
            'status' => 'completed',
        ])->assertUnauthorized();
    }

    /**
     * Akun lama IKUT melihat tur ini — sekali, sama seperti pendaftar baru.
     *
     * Sebagian besar yang dituntun tur ini memang baru (saldo, Enterprise,
     * bantuan, harga perkenalan), jadi pelanggan lama pun belum pernah
     * melihatnya. Yang dijaga tetap "sekali saja", dan itu dijamin baris di
     * `user_guide_progress` — bukan oleh siapa yang mendaftar kapan.
     */
    public function test_akun_lama_ikut_melihat_tur_sekali(): void
    {
        $lama = User::create([
            'name' => 'Pelanggan Lama',
            'email' => 'lama@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $workspace = Workspace::create([
            'name' => 'Toko Lama',
            'slug' => 'toko-lama',
            'owner_id' => $lama->id,
            'owner_email' => $lama->email,
            'max_sessions' => 1,
            'monthly_message_quota' => 1000,
        ]);

        $workspace->members()->attach($lama->id, ['role' => 'owner']);

        $sesi = fn () => $this->actingAs($lama)
            ->withSession(['current_workspace_id' => $workspace->id]);

        // Kunjungan pertama: turnya muncul.
        $sesi()->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-tur-pengenalan', false);

        $sesi()->postJson(route('onboarding.guides.ack', ['guideKey' => self::KUNCI]), [
            'status' => 'completed',
        ])->assertOk();

        // Kunjungan berikutnya: tidak lagi. "Sekali" berlaku untuk semua orang,
        // bukan cuma untuk pendaftar baru.
        $sesi()->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('data-tur-pengenalan', false);
    }

    /**
     * Migrasinya TIDAK boleh menandai siapa pun sudah melihat.
     *
     * Kalau ia mengisi tabel lagi seperti versi pertamanya, akun lama kembali
     * dilewati tanpa satu pun tes lain yang gagal — dan tidak ada yang
     * menyadarinya sampai ada yang bertanya kenapa turnya tidak pernah muncul.
     */
    public function test_migrasi_tidak_menandai_akun_lama_sudah_melihat(): void
    {
        User::create([
            'name' => 'Pelanggan Lama',
            'email' => 'lama@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->assertSame(0, UserGuideProgress::count());
    }

    /**
     * Versi yang dinaikkan menampilkan tur lagi kepada yang sudah melihat versi
     * lama — tanpa menghapus jejak bahwa mereka pernah dituntun.
     */
    public function test_versi_yang_dinaikkan_menampilkan_tur_lagi(): void
    {
        $user = $this->daftar();

        UserGuideProgress::create([
            'user_id' => $user->id,
            'guide_key' => self::KUNCI,
            'guide_version' => 1,
            'status' => 'completed',
        ]);

        $this->assertTrue($user->fresh()->hasSeenGuide(self::KUNCI, 1));
        $this->assertFalse($user->fresh()->hasSeenGuide(self::KUNCI, 2));
    }

    /**
     * Setiap langkah yang menunjuk menu harus benar-benar menemukan menunya.
     *
     * Tur yang menunjuk sudut kosong layar lebih buruk daripada tur yang tidak
     * ada — dan penandanya memakai nama rute justru supaya ia tidak ikut rusak
     * saat menunya ditata ulang.
     */
    public function test_setiap_langkah_menunjuk_menu_yang_benar_benar_ada(): void
    {
        $this->daftar();

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        preg_match_all('/data-tur-menu="([a-z0-9._-]+)"/i', $html, $adaDiMenu);
        $menu = array_unique($adaDiMenu[1]);

        /*
         | Blok turnya dibaca sebagai JSON, persis seperti yang dilakukan
         | peramban — bukan dengan regex atas HTML mentah, tempat tanda kutip
         | sudah berubah menjadi ' oleh JSON_HEX_APOS dan cocok-cocokan
         | teksnya berubah jadi menebak. Tes yang menebak akan gagal karena
         | alasan yang bukan bug, dan itu melatih orang mengabaikannya.
        */
        preg_match('#<script type="application/json" data-tur-pengenalan>(.*?)</script>#s', $html, $blok);

        $this->assertNotEmpty($blok, 'Blok data tur tidak ditemukan di halaman.');

        $konfigurasi = json_decode($blok[1], true, 512, JSON_THROW_ON_ERROR);

        $target = collect($konfigurasi['langkah'])
            ->pluck('target')
            ->filter()
            ->map(fn ($t) => trim($t, "[]'\" "))
            ->map(fn ($t) => str_replace('data-tur-menu=', '', $t))
            ->map(fn ($t) => trim($t, "'\"[] "))
            ->unique()
            ->all();

        $this->assertNotEmpty($menu, 'Menu tidak memasang penanda tur sama sekali.');
        $this->assertNotEmpty($target, 'Tur tidak menunjuk satu pun menu.');

        foreach ($target as $rute) {
            $this->assertContains(
                $rute,
                $menu,
                "Langkah tur menunjuk menu '{$rute}' yang tidak ada di sidebar — "
                    .'langkah itu akan menunjuk sudut kosong layar.',
            );
        }
    }
}
