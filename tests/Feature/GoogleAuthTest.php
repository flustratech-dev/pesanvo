<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setupGoogleConfig(): void
    {
        config([
            'services.google.client_id' => 'test-client-id.apps.googleusercontent.com',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'http://localhost:8051/auth/google/callback',
        ]);
    }

    protected function makeSocialiteUser(string $email, string $id = '1234567890', string $name = 'Google Tester'): SocialiteUser
    {
        $user = new SocialiteUser;
        $user->map([
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'avatar' => 'https://lh3.googleusercontent.com/a/default-user',
        ]);

        return $user;
    }

    public function test_tanpa_kredensial_google_redirect_mengarahkan_ke_login_dengan_pesan(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
        ]);

        $response = $this->get(route('auth.google'));
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
    }

    public function test_dengan_kredensial_google_redirect_mengarah_ke_google(): void
    {
        $this->setupGoogleConfig();

        $response = $this->get(route('auth.google'));
        $response->assertRedirect();
        $this->assertStringContainsString('accounts.google.com', $response->headers->get('Location'));
    }

    public function test_akun_google_belum_terdaftar_diarahkan_ke_halaman_register_dengan_alert_dan_data_terisi(): void
    {
        $this->setupGoogleConfig();

        $socialiteUser = $this->makeSocialiteUser('calon.member@pesanvo.id', '9988776655', 'Calon Member');
        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('register'));
        $response->assertSessionHas('google_email', 'calon.member@pesanvo.id');
        $response->assertSessionHas('google_name', 'Calon Member');
        $response->assertSessionHas('google_id', '9988776655');
        $response->assertSessionHas('info');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'calon.member@pesanvo.id']);
    }

    public function test_pendaftaran_dengan_data_google_tersimpan_dan_email_terverifikasi(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Calon Member',
            'email' => 'calon.member@pesanvo.id',
            'workspace' => 'Kopi Senja Store',
            'password' => 'Rahasia1234#',
            'password_confirmation' => 'Rahasia1234#',
            'google_id' => '9988776655',
            'google_avatar' => 'https://lh3.googleusercontent.com/a/default-user',
            'terms' => '1',
        ]);

        $response->assertRedirect(route('sessions.index'));

        $user = User::where('email', 'calon.member@pesanvo.id')->first();
        $this->assertNotNull($user);
        $this->assertSame('9988776655', $user->google_id);
        $this->assertSame('https://lh3.googleusercontent.com/a/default-user', $user->avatar);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);

        $workspace = Workspace::where('name', 'Kopi Senja Store')->first();
        $this->assertNotNull($workspace);
        $this->assertSame($user->id, $workspace->owner_id);
    }

    public function test_akun_lama_bisa_login_dengan_google_dan_ditautkan(): void
    {
        $this->setupGoogleConfig();

        $existingUser = User::create([
            'name' => 'Member Pesanvo',
            'email' => 'member.lama@pesanvo.id',
            'password' => Hash::make('PasswordLama123#'),
        ]);

        $workspace = Workspace::create([
            'name' => 'Workspace Lama',
            'slug' => 'workspace-lama',
            'owner_id' => $existingUser->id,
            'owner_email' => $existingUser->email,
        ]);
        $workspace->members()->attach($existingUser->id, ['role' => 'owner']);

        $socialiteUser = $this->makeSocialiteUser('member.lama@pesanvo.id', '4455667788', 'Member Pesanvo');
        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($existingUser);

        $existingUser->refresh();
        $this->assertSame('4455667788', $existingUser->google_id);
        $this->assertNotNull($existingUser->last_login_at);
    }
}
