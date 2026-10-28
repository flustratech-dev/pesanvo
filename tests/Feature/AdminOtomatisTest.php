<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Admin terbentuk dan disamakan dengan env setiap deploy (start.sh menjalankan
 * AdminUserSeeder tiap boot). Produksi pertama dimulai dari database kosong,
 * dan admin yang bergantung pada `db:seed` manual tidak pernah terbentuk.
 */
class AdminOtomatisTest extends TestCase
{
    use RefreshDatabase;

    private function adminEnv(array $timpa = []): void
    {
        config(['pesanvo.admin' => array_merge([
            'name' => 'Administrator',
            'email' => 'admin@gmail.com',
            'password' => 'sandi-dari-env',
            'phone' => '6285808749131',
            'company' => 'Pesanvo Cloud Indonesia',
        ], $timpa)]);
    }

    public function test_database_kosong_langsung_punya_admin(): void
    {
        $this->adminEnv();

        $this->seed(AdminUserSeeder::class);

        $admin = User::where('email', 'admin@gmail.com')->firstOrFail();
        $this->assertTrue((bool) $admin->is_super_admin);
        $this->assertTrue(Hash::check('sandi-dari-env', $admin->password));

        $this->post(route('login'), ['email' => 'admin@gmail.com', 'password' => 'sandi-dari-env'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_kata_sandi_env_yang_diganti_ikut_berlaku_pada_deploy_berikutnya(): void
    {
        $this->adminEnv();
        $this->seed(AdminUserSeeder::class);

        $this->adminEnv(['password' => 'sandi-baru-dari-env']);
        $this->seed(AdminUserSeeder::class);

        $this->assertTrue(Hash::check('sandi-baru-dari-env', User::first()->password));
        $this->assertSame(1, User::count(), 'Tidak boleh lahir admin kedua.');
    }

    public function test_akun_biasa_dengan_email_admin_diangkat_jadi_super_admin(): void
    {
        $this->adminEnv();
        User::create(['name' => 'Pendaftar', 'email' => 'admin@gmail.com', 'password' => Hash::make('apa-saja')]);

        $this->seed(AdminUserSeeder::class);

        $admin = User::first();
        $this->assertTrue((bool) $admin->is_super_admin);
        $this->assertSame('Administrator', $admin->name);
        $this->assertTrue(Hash::check('sandi-dari-env', $admin->password));
    }

    public function test_produksi_tanpa_admin_password_tidak_membuat_admin_bersandi_bawaan(): void
    {
        $this->adminEnv(['password' => null]);
        $this->app['env'] = 'production';

        // Dipanggil langsung: `db:seed` di lingkungan production meminta
        // konfirmasi, sedangkan yang diuji di sini isi seeder-nya.
        app(AdminUserSeeder::class)->run();

        $this->assertSame(0, User::count());
    }

    public function test_hash_tidak_ditulis_ulang_kalau_kata_sandi_sama(): void
    {
        $this->adminEnv();
        $this->seed(AdminUserSeeder::class);
        $hashAwal = User::first()->getRawOriginal('password');

        $this->seed(AdminUserSeeder::class);

        $this->assertSame($hashAwal, User::first()->getRawOriginal('password'));
    }
}
