<?php

namespace Tests\Feature;

use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_lupa_kata_sandi_terbuka(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
        $response->assertSee('Lupa Kata Sandi');
    }

    public function test_kirim_tautan_reset_ke_email_terdaftar(): void
    {
        Mail::fake();

        $user = User::create([
            'name' => 'Budi Pengguna',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('password-lama-123'),
        ]);

        $response = $this->post(route('password.email'), [
            'email' => 'budi@contoh.id',
        ]);

        $response->assertSessionHas('status');

        Mail::assertSent(ResetPasswordMail::class, function ($mail) {
            return $mail->hasTo('budi@contoh.id');
        });

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'budi@contoh.id',
        ]);
    }

    public function test_kirim_tautan_ke_email_tidak_dikenal_tidak_membocorkan_informasi(): void
    {
        Mail::fake();

        $response = $this->post(route('password.email'), [
            'email' => 'tidakada@contoh.id',
        ]);

        $response->assertSessionHas('status');
        Mail::assertNothingSent();
    }

    public function test_halaman_reset_password_terbuka_dengan_token(): void
    {
        $response = $this->get(route('password.reset', [
            'token' => 'token-percobaan-123',
            'email' => 'budi@contoh.id',
        ]));

        $response->assertOk();
        $response->assertSee('Kata Sandi Baru');
    }

    public function test_reset_kata_sandi_berhasil_memperbarui_kata_sandi(): void
    {
        $user = User::create([
            'name' => 'Budi Pengguna',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('password-lama-123'),
        ]);

        $rawToken = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => 'budi@contoh.id',
            'token' => Hash::make($rawToken),
            'created_at' => now(),
        ]);

        $response = $this->post(route('password.update'), [
            'token' => $rawToken,
            'email' => 'budi@contoh.id',
            'password' => 'password-baru-456',
            'password_confirmation' => 'password-baru-456',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');

        $this->assertTrue(Hash::check('password-baru-456', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'budi@contoh.id',
        ]);
    }

    public function test_reset_kata_sandi_gagal_bila_token_salah(): void
    {
        $user = User::create([
            'name' => 'Budi Pengguna',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('password-lama-123'),
        ]);

        DB::table('password_reset_tokens')->insert([
            'email' => 'budi@contoh.id',
            'token' => Hash::make('token-asli-123'),
            'created_at' => now(),
        ]);

        $response = $this->post(route('password.update'), [
            'token' => 'token-palsu-999',
            'email' => 'budi@contoh.id',
            'password' => 'password-baru-456',
            'password_confirmation' => 'password-baru-456',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password-lama-123', $user->fresh()->password));
    }

    public function test_reset_kata_sandi_gagal_bila_token_kedaluwarsa(): void
    {
        $user = User::create([
            'name' => 'Budi Pengguna',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('password-lama-123'),
        ]);

        $rawToken = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => 'budi@contoh.id',
            'token' => Hash::make($rawToken),
            'created_at' => now()->subMinutes(61), // > 60 menit
        ]);

        $response = $this->post(route('password.update'), [
            'token' => $rawToken,
            'email' => 'budi@contoh.id',
            'password' => 'password-baru-456',
            'password_confirmation' => 'password-baru-456',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password-lama-123', $user->fresh()->password));
    }
}
