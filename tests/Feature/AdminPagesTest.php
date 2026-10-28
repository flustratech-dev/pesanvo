<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Setiap halaman panel admin harus benar-benar terbuka.
 *
 * Halaman panel jarang dilihat sampai ada masalah — dan saat itu terjadi,
 * halaman yang pecah berarti tidak ada yang bisa memeriksa apa pun. Tes ini
 * sengaja memuat halaman dengan data yang ADA isinya, bukan database kosong:
 * hampir semua kesalahan render di sini muncul dari baris pertama, bukan dari
 * keadaan kosong.
 */
class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->admin = User::create([
            'name' => 'Pesanvo Finance',
            'email' => 'finance@pesanvo.id',
            'password' => Hash::make('rahasia12345'),
            'is_super_admin' => true,
        ]);

        $pelanggan = User::create([
            'name' => 'Owner',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Toko Contoh',
            'slug' => 'toko-contoh',
            'owner_id' => $pelanggan->id,
            'owner_email' => $pelanggan->email,
            'billing_phone' => '6281234567890',
            'max_sessions' => 1,
            'monthly_message_quota' => 3000,
        ]);

        $this->workspace->members()->attach($pelanggan->id, ['role' => 'owner']);
        $this->berlangganan($this->workspace);

        // Isi secukupnya supaya tiap tabel punya baris untuk dirender.
        $sesi = $this->workspace->sessions()->create([
            'name' => 'CS Utama',
            'status' => 'connected',
            'phone_number' => '6281111111111',
            'connected_at' => now(),
        ]);

        $this->workspace->messages()->create([
            'wa_session_id' => $sesi->id,
            'direction' => 'outbound',
            'chat_id' => '6282222222222@c.us',
            'to_number' => '6282222222222',
            'type' => 'text',
            'body' => 'halo',
            'status' => 'failed',
            'error' => 'Sesi tidak tersambung.',
        ]);

        app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        AuditLog::record('admin.plan_changed', $this->workspace, ['plan' => 'prime'], $this->workspace->id);
    }

    public static function halaman(): array
    {
        return [
            'ringkasan' => ['admin.overview'],
            'workspace' => ['admin.workspaces'],
            'tagihan' => ['admin.invoices'],
            'sesi' => ['admin.sessions'],
            'pesan' => ['admin.messages'],
            'pengguna' => ['admin.users'],
            'audit' => ['admin.audit'],
            'sistem' => ['admin.system'],
            'status' => ['admin.status'],
        ];
    }

    #[DataProvider('halaman')]
    public function test_halaman_panel_terbuka_dengan_data(string $rute): void
    {
        $this->actingAs($this->admin)->get(route($rute))->assertOk();
    }

    #[DataProvider('halaman')]
    public function test_halaman_panel_tertutup_untuk_bukan_super_admin(string $rute): void
    {
        $biasa = User::create([
            'name' => 'Orang Lain',
            'email' => 'lain@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        // 404, bukan 403: bagi yang bukan super admin, panel ini sebaiknya tidak
        // tampak pernah ada.
        $this->actingAs($biasa)->get(route($rute))->assertNotFound();
    }

    public function test_halaman_detail_workspace_menampilkan_isinya(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.workspaces.show', $this->workspace->id))
            ->assertOk()
            ->assertSee($this->workspace->name)
            ->assertSee('CS Utama')
            ->assertSee('Kelonggaran batas');
    }

    public function test_daftar_workspace_menuju_halaman_detail(): void
    {
        // Daftar hanya untuk mencari; tindakannya di halaman sendiri. Kalau
        // tautannya hilang, daftar itu jadi buntu.
        $this->actingAs($this->admin)
            ->get(route('admin.workspaces'))
            ->assertOk()
            ->assertSee(route('admin.workspaces.show', $this->workspace->id));
    }

    public function test_saringan_lalu_lintas_pesan_bekerja(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.messages', ['status' => 'failed']))
            ->assertOk()
            ->assertSee('Sesi tidak tersambung.');

        $this->actingAs($this->admin)
            ->get(route('admin.messages', ['status' => 'read']))
            ->assertOk()
            ->assertDontSee('Sesi tidak tersambung.');
    }

    public function test_catatan_audit_bisa_disaring_per_kelompok(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.audit', ['kelompok' => 'Tindakan admin']))
            ->assertOk()
            ->assertSee('admin.plan_changed');

        $this->actingAs($this->admin)
            ->get(route('admin.audit', ['kelompok' => 'Workspace & kunci']))
            ->assertOk()
            ->assertDontSee('admin.plan_changed');
    }

    /**
     * Halaman sistem harus tetap terbuka saat engine tidak terjangkau —
     * justru keadaan itulah yang paling perlu dilihat di sana.
     */
    public function test_halaman_sistem_tetap_terbuka_saat_engine_mati(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->actingAs($this->admin)
            ->get(route('admin.system'))
            ->assertOk()
            ->assertSee('Engine WhatsApp');
    }

    public function test_halaman_sistem_menampilkan_metrik_baileys_dan_tanpa_alarm_palsu(): void
    {
        Http::swap(new Factory);
        Http::fake([
            '*' => Http::response([
                'status' => 'ok',
                'engine' => 'baileys',
                'sessions' => 2,
                'connected_sessions' => 1,
                'connecting_sessions' => 1,
                'qr_sessions' => 0,
                'max_sessions' => 10,
                'memory' => [
                    'rss_mb' => 78,
                    'heap_used_mb' => 34,
                    'heap_total_mb' => 45,
                ],
                'uptime_seconds' => 3600,
            ]),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.system'))
            ->assertOk()
            ->assertSee('Sesi aktif di engine')
            ->assertSee('78 MB RSS')
            ->assertSee('1 terhubung')
            ->assertSee('1 menghubungkan')
            ->assertDontSee('Chromium')
            ->assertDontSee('Proses Chromium hidup');
    }
}
