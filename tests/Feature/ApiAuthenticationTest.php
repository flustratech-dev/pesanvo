<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): Workspace
    {
        return Workspace::create([
            'name' => 'Contoh',
            'slug' => 'contoh',
            'max_sessions' => 2,
            'monthly_message_quota' => 100,
            'api_rate_limit_per_minute' => 60,
        ]);
    }

    public function test_request_tanpa_api_key_ditolak(): void
    {
        $this->getJson('/api/v1/health')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_api_key_valid_diterima(): void
    {
        [, $plain] = ApiKey::issue($this->workspace(), 'kunci uji');

        $this->withHeader('X-Api-Key', $plain)
            ->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('data.workspace', 'Contoh');
    }

    /**
     * Baris counter yang baru dibuat harus melaporkan 0, bukan null. Kalau null
     * lolos, API menampilkan null ke pemanggil dan pemeriksaan kuota jadi
     * bergantung pada cara PHP membandingkan null dengan angka.
     */
    public function test_pemakaian_workspace_baru_dilaporkan_nol_bukan_null(): void
    {
        [, $plain] = ApiKey::issue($this->workspace(), 'kunci uji');

        $this->withHeader('X-Api-Key', $plain)
            ->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('data.usage.messages_sent', 0)
            ->assertJsonPath('data.usage.messages_received', 0)
            ->assertJsonPath('data.usage.messages_failed', 0);
    }

    public function test_api_key_bisa_dikirim_sebagai_bearer_token(): void
    {
        [, $plain] = ApiKey::issue($this->workspace(), 'kunci uji');

        $this->withHeader('Authorization', "Bearer {$plain}")
            ->getJson('/api/v1/health')
            ->assertOk();
    }

    public function test_api_key_yang_dicabut_ditolak(): void
    {
        [$key, $plain] = ApiKey::issue($this->workspace(), 'kunci uji');
        $key->update(['revoked_at' => now()]);

        $this->withHeader('X-Api-Key', $plain)
            ->getJson('/api/v1/health')
            ->assertStatus(401);
    }

    public function test_workspace_yang_disuspend_ditolak(): void
    {
        $workspace = $this->workspace();
        [, $plain] = ApiKey::issue($workspace, 'kunci uji');
        $workspace->update(['status' => 'suspended']);

        $this->withHeader('X-Api-Key', $plain)
            ->getJson('/api/v1/health')
            ->assertStatus(403);
    }

    /**
     * Scope otp memberi kemampuan mengirim kode verifikasi ke nomor mana pun,
     * jadi kunci integrasi biasa tidak boleh bisa memakainya.
     */
    public function test_kunci_tanpa_scope_otp_tidak_bisa_mengirim_otp(): void
    {
        [, $plain] = ApiKey::issue($this->workspace(), 'kunci integrasi', ['messages']);

        $this->withHeader('X-Api-Key', $plain)
            ->postJson('/api/v1/otp/send', ['phone' => '081234567890'])
            ->assertStatus(403);
    }

    public function test_kunci_workspace_lain_tidak_bisa_melihat_sesi_kita(): void
    {
        $a = $this->workspace();
        $b = Workspace::create(['name' => 'Lain', 'slug' => 'lain', 'max_sessions' => 1]);

        $sessionA = $a->sessions()->create(['name' => 'CS', 'status' => 'connected']);

        [, $plainB] = ApiKey::issue($b, 'kunci b');

        $this->withHeader('X-Api-Key', $plainB)
            ->getJson("/api/v1/sessions/{$sessionA->id}")
            ->assertStatus(404);
    }
}
