<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Budi Pengguna',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('password-lama-123'),
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Workspace Budi',
            'slug' => 'workspace-budi',
            'owner_id' => $this->user->id,
            'owner_email' => $this->user->email,
        ]);

        $this->workspace->members()->attach($this->user->id, ['role' => 'owner']);
    }

    public function test_halaman_profil_terbuka_untuk_user_login(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('profile.show'));

        $response->assertOk();
        $response->assertSee('Profil Akun');
        $response->assertSee('Budi Pengguna');
        $response->assertSee('budi@contoh.id');
    }

    public function test_update_nama_profil_berhasil(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->put(route('profile.update'), [
                'name' => 'Budi Santoso',
            ]);

        $response->assertRedirect();
        $this->assertEquals('Budi Santoso', $this->user->fresh()->name);
    }

    public function test_update_password_berhasil_dengan_password_lama_benar(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->put(route('profile.password'), [
                'current_password' => 'password-lama-123',
                'password' => 'password-baru-789',
                'password_confirmation' => 'password-baru-789',
            ]);

        $response->assertRedirect();
        $this->assertTrue(Hash::check('password-baru-789', $this->user->fresh()->password));
    }

    public function test_update_password_gagal_bila_password_lama_salah(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->put(route('profile.password'), [
                'current_password' => 'password-salah',
                'password' => 'password-baru-789',
                'password_confirmation' => 'password-baru-789',
            ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('password-lama-123', $this->user->fresh()->password));
    }

    public function test_update_detail_profil_tambahan_berhasil(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->put(route('profile.update'), [
                'name' => 'Budi Santoso',
                'phone' => '081234567890',
                'company' => 'PT Digital Pesanvo',
                'city' => 'Bandung',
                'address' => 'Jl. Asia Afrika No. 10',
                'bio' => 'Pengembang aplikasi web dan SaaS.',
            ]);

        $response->assertRedirect();
        $user = $this->user->fresh();
        $this->assertEquals('Budi Santoso', $user->name);
        $this->assertEquals('081234567890', $user->phone);
        $this->assertEquals('PT Digital Pesanvo', $user->company);
        $this->assertEquals('Bandung', $user->city);
        $this->assertEquals('Jl. Asia Afrika No. 10', $user->address);
        $this->assertEquals('Pengembang aplikasi web dan SaaS.', $user->bio);
    }

    public function test_upload_foto_profil_berhasil(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('foto_profil.jpg', 300, 300);

        $response = $this->actingAs($this->user)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->put(route('profile.update'), [
                'name' => 'Budi Santoso',
                'avatar' => $file,
            ]);

        $response->assertRedirect();
        $user = $this->user->fresh();
        $this->assertNotNull($user->avatar);
        Storage::disk('public')->assertExists($user->avatar);
        $this->assertStringContainsString('storage/', $user->avatarUrl());
    }
}
