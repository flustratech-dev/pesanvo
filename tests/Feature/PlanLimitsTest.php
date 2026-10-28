<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Batas yang berasal dari paket.
 *
 * Sebelum penagihan ada, sebagian batas ini tercatat di database tanpa pernah
 * ditegakkan di mana pun — halaman harga menjanjikan angka yang tidak berlaku.
 * Tes di kelas ini yang menjaga janji itu tetap benar.
 */
class PlanLimitsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Toko Contoh',
            'slug' => 'toko-contoh',
            'owner_id' => $this->owner->id,
            'owner_email' => $this->owner->email,
            'max_sessions' => 1,
            'monthly_message_quota' => 3000,
            'api_rate_limit_per_minute' => 60,
        ]);

        $this->workspace->members()->attach($this->owner->id, ['role' => 'owner']);

        // Workspace baru lahir `unpaid`; tes ini menguji hal lain,
        // jadi penagihannya tidak boleh ikut menghalangi.
        $this->berlangganan($this->workspace, 'essentials');

    }

    public function test_api_key_dibatasi_jumlahnya_oleh_paket(): void
    {
        // Essentials mengizinkan tiga.
        for ($i = 1; $i <= 3; $i++) {
            $this->actingAs($this->owner)
                ->post(route('api-keys.store'), ['name' => "kunci {$i}"])
                ->assertRedirect();
        }

        $this->actingAs($this->owner)
            ->post(route('api-keys.store'), ['name' => 'kunci keempat'])
            ->assertSessionHasErrors('name');

        $this->assertSame(3, $this->workspace->apiKeys()->count());
    }

    public function test_kunci_yang_dicabut_membebaskan_jatahnya(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            ApiKey::issue($this->workspace, "kunci {$i}");
        }

        $this->workspace->apiKeys()->first()->forceFill(['revoked_at' => now()])->save();

        // Kunci yang sudah dicabut tidak bisa dipakai mengirim apa pun, jadi
        // menghitungnya sebagai jatah terpakai berarti menghukum pelanggan
        // karena membereskan kuncinya sendiri.
        $this->assertTrue($this->workspace->fresh()->canAddApiKey());
    }

    public function test_anggota_tim_dibatasi_jumlahnya_oleh_paket(): void
    {
        // Essentials mengizinkan dua anggota; owner sudah satu.
        $kedua = User::create([
            'name' => 'Maya', 'email' => 'maya@contoh.id', 'password' => Hash::make('rahasia12345'),
        ]);
        $ketiga = User::create([
            'name' => 'Budi', 'email' => 'budi@contoh.id', 'password' => Hash::make('rahasia12345'),
        ]);

        $this->actingAs($this->owner)
            ->post(route('settings.members.add'), ['email' => $kedua->email, 'role' => 'member'])
            ->assertRedirect();

        $this->actingAs($this->owner)
            ->post(route('settings.members.add'), ['email' => $ketiga->email, 'role' => 'member'])
            ->assertSessionHasErrors('email');

        $this->assertSame(2, $this->workspace->members()->count());
    }

    public function test_paket_elite_tidak_membatasi_api_key(): void
    {
        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'elite', 'monthly');
        app(SubscriptionService::class)->markPaid($invoice);

        for ($i = 1; $i <= 6; $i++) {
            ApiKey::issue($this->workspace, "kunci {$i}");
        }

        $this->assertTrue($this->workspace->fresh()->canAddApiKey());
    }

    public function test_batas_permintaan_api_per_menit_ditegakkan(): void
    {
        $this->workspace->forceFill(['api_rate_limit_per_minute' => 2])->save();

        [, $key] = ApiKey::issue($this->workspace, 'kunci uji');

        $this->withHeader('X-Api-Key', $key)->getJson('/api/v1/health')->assertOk();
        $this->withHeader('X-Api-Key', $key)->getJson('/api/v1/health')->assertOk();

        $this->withHeader('X-Api-Key', $key)
            ->getJson('/api/v1/health')
            ->assertStatus(429);
    }

    public function test_batas_dihitung_per_workspace_bukan_per_kunci(): void
    {
        $this->workspace->forceFill(['api_rate_limit_per_minute' => 2])->save();

        [, $satu] = ApiKey::issue($this->workspace, 'kunci satu');
        [, $dua] = ApiKey::issue($this->workspace, 'kunci dua');

        $this->withHeader('X-Api-Key', $satu)->getJson('/api/v1/health')->assertOk();
        $this->withHeader('X-Api-Key', $satu)->getJson('/api/v1/health')->assertOk();

        // Kalau dihitung per kunci, batasnya bisa dilipatgandakan hanya dengan
        // membuat kunci baru — yang justru gratis.
        $this->withHeader('X-Api-Key', $dua)
            ->getJson('/api/v1/health')
            ->assertStatus(429);
    }

    public function test_workspace_internal_tidak_dibatasi(): void
    {
        $this->workspace->forceFill(['is_internal' => true])->save();

        for ($i = 1; $i <= 10; $i++) {
            ApiKey::issue($this->workspace, "kunci {$i}");
        }

        $this->assertTrue($this->workspace->fresh()->canAddApiKey());
        $this->assertTrue($this->workspace->fresh()->canAddMember());
    }

    public function test_retensi_riwayat_pesan_mengikuti_paket(): void
    {
        $this->assertSame(30, $this->workspace->fresh()->messageRetentionDays());

        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'elite', 'monthly');
        app(SubscriptionService::class)->markPaid($invoice);

        $this->assertSame(365, $this->workspace->fresh()->messageRetentionDays());
    }
}
