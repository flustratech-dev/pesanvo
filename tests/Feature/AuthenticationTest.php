<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_landing_bisa_diakses_publik(): void
    {
        $this->get('/')->assertOk()->assertSee('Pesanvo Gateway');
    }

    public function test_dashboard_menolak_tamu_dan_mengarahkan_ke_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    /**
     * Rute dashboard dan REST API sama-sama punya konsep "sessions". Kalau
     * keduanya memakai nama rute yang sama, route('sessions.index') akan
     * menghasilkan URL API dan pengguna yang baru mendaftar dilempar ke JSON,
     * bukan ke dashboard. Assertion terhadap path harfiah di bawah menjaga itu.
     */
    public function test_pendaftaran_mengarahkan_ke_halaman_sesi_dashboard(): void
    {
        $this->post('/register', [
            'name' => 'Citra',
            'email' => 'citra@contoh.id',
            'workspace' => 'Toko Citra',
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
            'terms' => '1',
        ])->assertRedirect('/sessions');

        $this->assertSame(url('/sessions'), route('sessions.index'));
    }

    public function test_pendaftaran_membuat_pengguna_workspace_dan_kepemilikannya(): void
    {
        $this->post('/register', [
            'name' => 'Budi',
            'email' => 'Budi@Contoh.ID',
            'workspace' => 'Toko Makmur',
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
            'terms' => '1',
        ])->assertRedirect('/sessions');

        $user = User::first();

        // Email disimpan huruf kecil supaya "Budi@..." dan "budi@..." tidak
        // pernah menjadi dua akun berbeda.
        $this->assertSame('budi@contoh.id', $user->email);
        $this->assertAuthenticatedAs($user);

        $workspace = Workspace::first();
        $this->assertSame('Toko Makmur', $workspace->name);
        $this->assertSame('owner', $user->fresh()->workspaces->first()->pivot->role);
    }

    /**
     * Formulir menandai centang persetujuan sebagai wajib, tapi atribut
     * `required` di HTML hanya berlaku di browser. Persetujuan ini harus
     * benar-benar tercatat, jadi servernya yang menolak.
     */
    public function test_pendaftaran_ditolak_tanpa_menyetujui_syarat_dan_ketentuan(): void
    {
        $this->post('/register', [
            'name' => 'Dewi',
            'email' => 'dewi@contoh.id',
            'workspace' => 'Toko Dewi',
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ])->assertSessionHasErrors('terms');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_pendaftaran_menolak_email_yang_sudah_terpakai(): void
    {
        User::create(['name' => 'Ada', 'email' => 'ada@contoh.id', 'password' => Hash::make('rahasia12345')]);

        $this->post('/register', [
            'name' => 'Ada Lain',
            'email' => 'ada@contoh.id',
            'workspace' => 'Workspace Lain',
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
            'terms' => '1',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_workspace_dengan_nama_sama_tetap_dapat_slug_unik(): void
    {
        foreach ([['a@contoh.id', 'Ana'], ['b@contoh.id', 'Bara']] as [$email, $nama]) {
            $this->post('/register', [
                'name' => $nama,
                'email' => $email,
                'workspace' => 'Toko Makmur',
                'password' => 'rahasia12345',
                'password_confirmation' => 'rahasia12345',
                'terms' => '1',
            ]);

            $this->post('/logout');
        }

        $slugs = Workspace::pluck('slug')->all();

        $this->assertCount(2, $slugs);
        $this->assertSame($slugs, array_unique($slugs), 'Slug workspace bertabrakan.');
    }

    public function test_login_berhasil_dengan_kredensial_benar(): void
    {
        $user = User::create([
            'name' => 'Ada',
            'email' => 'ada@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->post('/login', ['email' => 'ada@contoh.id', 'password' => 'rahasia12345'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    /**
     * Pesan galat harus sama untuk email tidak dikenal maupun password salah,
     * supaya form login tidak bisa dipakai memetakan email mana yang terdaftar.
     */
    public function test_pesan_galat_tidak_membocorkan_email_mana_yang_terdaftar(): void
    {
        User::create(['name' => 'Ada', 'email' => 'ada@contoh.id', 'password' => Hash::make('rahasia12345')]);

        $salahSandi = $this->post('/login', ['email' => 'ada@contoh.id', 'password' => 'sandisalah'])
            ->assertSessionHasErrors('email');

        $this->flushSession();

        $tidakDikenal = $this->post('/login', ['email' => 'entah@contoh.id', 'password' => 'sandisalah'])
            ->assertSessionHasErrors('email');

        $this->assertSame(
            $salahSandi->getSession()->get('errors')->first('email'),
            $tidakDikenal->getSession()->get('errors')->first('email'),
        );

        $this->assertGuest();
    }

    public function test_logout_mengakhiri_sesi(): void
    {
        $user = User::create([
            'name' => 'Ada',
            'email' => 'ada@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->actingAs($user)->post('/logout')->assertRedirect(route('welcome'));

        $this->assertGuest();
    }

    public function test_pengguna_yang_sudah_masuk_tidak_melihat_form_login(): void
    {
        $user = User::create([
            'name' => 'Ada',
            'email' => 'ada@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->actingAs($user)->get('/login')->assertRedirect();
    }
}
