<?php

namespace Tests\Feature;

use App\Jobs\BillingCycleJob;
use App\Models\Invoice;
use App\Models\User;
use App\Models\WaSession;
use App\Models\Workspace;
use App\Services\Billing\SubscriptionService;
use App\Services\MessageDispatcher;
use App\Services\Notifications\EmailNotifier;
use App\Services\Notifications\WhatsAppNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Rekanan: paket berbayar yang diberikan admin tanpa pembayaran.
 *
 * Yang dijaga di sini adalah bahwa hasilnya TIDAK bisa dibedakan dari
 * langganan yang dibayar — batas paket, tanggal berakhir, dan penghentian saat
 * habis — kecuali dua hal: tidak ada tagihan, dan perpanjangannya lewat admin.
 */
class RekananTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pemilik;

    private Workspace $workspace;

    private WaSession $sesi;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@pesanvo.id',
            'password' => Hash::make('rahasia12345'),
            'is_super_admin' => true,
        ]);

        $this->pemilik = User::create([
            'name' => 'Mitra',
            'email' => 'mitra@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Agensi Mitra',
            'slug' => 'agensi-mitra',
            'owner_id' => $this->pemilik->id,
            'owner_email' => $this->pemilik->email,
        ]);

        $this->workspace->members()->attach($this->pemilik->id, ['role' => 'owner']);

        // Lahir di paket coba gratis, persis seperti pendaftar sungguhan.
        app(SubscriptionService::class)->ensureFor($this->workspace);

        $this->sesi = $this->workspace->sessions()->create([
            'name' => 'CS',
            'status' => 'connected',
            'phone_number' => '6281111111111',
            'connected_at' => now(),
        ]);
    }

    private function beri(string $plan = 'prime', string $period = 'monthly'): void
    {
        $this->actingAs($this->admin)->post(route('admin.workspaces.partner', $this->workspace->id), [
            'plan' => $plan,
            'period' => $period,
        ])->assertSessionHasNoErrors();
    }

    private function kirim(int $berapa): void
    {
        for ($i = 1; $i <= $berapa; $i++) {
            app(MessageDispatcher::class)->queue(
                WaSession::with('workspace')->findOrFail($this->sesi->id),
                '6282222222222',
                ['body' => "halo {$i}"]
            );
        }
    }

    public function test_paket_langsung_aktif_dengan_batas_dan_tanggal_berakhir(): void
    {
        $this->beri('prime', 'monthly');

        $ws = $this->workspace->fresh();
        $sub = $ws->subscription;

        $this->assertTrue($ws->isPartner());
        $this->assertSame('prime', $sub->plan_slug);
        // `active`, BUKAN `trialing` — tombol ubah paket lama meninggalkan
        // `trialing` tanpa tanggal berakhir, alias paket berbayar selamanya.
        $this->assertSame('active', $sub->status);
        $this->assertNotNull($sub->current_period_end);
        $this->assertTrue($sub->current_period_end->between(now()->addMonth()->subMinute(), now()->addMonth()->addMinute()));
        $this->assertEquals($sub->current_period_end, $ws->service_until);
        $this->assertSame(10_000, $ws->monthly_message_quota);
        $this->assertFalse($ws->isFreeTier());
    }

    public function test_batas_lima_pesan_tidak_berlaku_lagi(): void
    {
        $this->beri();

        $this->kirim(8);

        $this->assertSame(8, $this->workspace->messages()->count());
    }

    public function test_dua_belas_bulan(): void
    {
        $this->beri('elite', 'yearly');

        $akhir = $this->workspace->fresh()->subscription->current_period_end;

        $this->assertTrue($akhir->between(now()->addYear()->subMinute(), now()->addYear()->addMinute()));
    }

    /**
     * Tidak ada tagihan yang dibuat — dan karena itu harga perkenalan pemiliknya
     * tidak hangus. Keduanya dibaca dari tagihan LUNAS.
     */
    public function test_tidak_membuat_tagihan_dan_harga_perkenalan_tetap_utuh(): void
    {
        $this->beri();

        $this->assertSame(0, Invoice::where('workspace_id', $this->workspace->id)->count());
        $this->assertTrue($this->workspace->fresh()->belumPernahBayar());
    }

    public function test_perpanjangan_menyambung_dari_sisa_masa(): void
    {
        $this->beri('prime', 'monthly');
        $akhirPertama = $this->workspace->fresh()->subscription->current_period_end;

        $this->actingAs($this->admin)->post(route('admin.workspaces.partner', $this->workspace->id), [
            'plan' => 'elite',
            'period' => 'monthly',
        ])->assertSessionHasNoErrors();

        $ws = $this->workspace->fresh();

        $this->assertSame('elite', $ws->subscription->plan_slug);
        $this->assertSame(50_000, $ws->monthly_message_quota);
        $this->assertTrue($ws->subscription->current_period_end->equalTo($akhirPertama->copy()->addMonth()));
    }

    public function test_tagihan_perpanjangan_tidak_terbit_otomatis(): void
    {
        $this->beri();

        $this->travelTo(now()->addMonth()->subDays(2));
        (new BillingCycleJob)->handle(
            app(SubscriptionService::class),
            app(WhatsAppNotifier::class),
            app(EmailNotifier::class),
        );

        $this->assertSame(0, Invoice::where('workspace_id', $this->workspace->id)->count());
    }

    /** Rekanan bukan pengecualian: masa yang habis tetap menghentikan pengiriman. */
    public function test_pengiriman_berhenti_saat_masa_habis(): void
    {
        $this->beri();

        $this->travelTo(now()->addMonth()->addDay());

        $this->expectException(RuntimeException::class);
        $this->kirim(1);
    }

    public function test_dicabut_kembali_ke_paket_coba(): void
    {
        $this->beri();

        $this->actingAs($this->admin)
            ->delete(route('admin.workspaces.partner.revoke', $this->workspace->id))
            ->assertSessionHasNoErrors();

        $ws = $this->workspace->fresh();

        $this->assertFalse($ws->isPartner());
        $this->assertTrue($ws->isFreeTier());
        $this->assertSame('trialing', $ws->subscription->status);
        $this->assertNull($ws->subscription->current_period_end);
        $this->assertSame(5, $ws->monthly_message_quota);
    }

    /** Membayar sendiri menjadikannya pelanggan biasa, dengan tagihan otomatis lagi. */
    public function test_membayar_sendiri_mengakhiri_status_rekanan(): void
    {
        $this->beri();

        $layanan = app(SubscriptionService::class);
        $tagihan = $layanan->issueInvoice($this->workspace->fresh(), 'prime', 'monthly');
        $layanan->markPaid($tagihan, $this->admin);

        $this->assertFalse($this->workspace->fresh()->isPartner());
    }

    public function test_paket_coba_dan_payg_tidak_bisa_diberikan(): void
    {
        foreach (['coba', 'payg', 'enterprise'] as $slug) {
            $this->actingAs($this->admin)->post(route('admin.workspaces.partner', $this->workspace->id), [
                'plan' => $slug,
                'period' => 'monthly',
            ])->assertSessionHasErrors('plan');
        }

        $this->assertFalse($this->workspace->fresh()->isPartner());
    }

    public function test_bukan_super_admin_tidak_bisa_membuka(): void
    {
        $this->actingAs($this->pemilik)->post(route('admin.workspaces.partner', $this->workspace->id), [
            'plan' => 'elite',
            'period' => 'yearly',
        ])->assertNotFound();

        $this->assertFalse($this->workspace->fresh()->isPartner());
    }

    public function test_halaman_detail_workspace_menampilkan_formnya(): void
    {
        $this->actingAs($this->admin)->get(route('admin.workspaces.show', $this->workspace->id))
            ->assertOk()
            ->assertSee('Aktifkan tanpa pembayaran');

        $this->beri();

        $this->actingAs($this->admin)->get(route('admin.workspaces.show', $this->workspace->id))
            ->assertOk()
            ->assertSee('Perpanjang rekanan')
            ->assertSee('Cabut status rekanan');
    }

    // ================= Tombol lama yang dulu bisa merusak keadaan =================

    /**
     * "Pindah paket" pada workspace coba gratis dulu menghasilkan paket berbayar
     * berstatus `trialing` tanpa tanggal berakhir — gratis selamanya.
     */
    public function test_pindah_paket_ditolak_untuk_workspace_coba_gratis(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.workspaces.plan', $this->workspace->id), ['plan' => 'elite'])
            ->assertSessionHasErrors('plan');

        $this->assertSame('coba', $this->workspace->fresh()->subscription->plan_slug);
    }

    public function test_tambah_hari_ditolak_untuk_workspace_coba_gratis(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.workspaces.extend', $this->workspace->id), ['hari' => 30])
            ->assertSessionHasErrors('hari');

        $this->assertNull($this->workspace->fresh()->subscription->current_period_end);
    }

    /** PAYG dibatasi saldo; tanggal berakhir akan mematikannya tanpa alasan. */
    public function test_tombol_lama_ditolak_untuk_payg(): void
    {
        app(SubscriptionService::class)->switchToPayg($this->workspace->fresh());

        $this->actingAs($this->admin)
            ->post(route('admin.workspaces.extend', $this->workspace->id), ['hari' => 30])
            ->assertSessionHasErrors('hari');
        $this->actingAs($this->admin)
            ->post(route('admin.workspaces.plan', $this->workspace->id), ['plan' => 'prime'])
            ->assertSessionHasErrors('plan');

        $ws = $this->workspace->fresh();
        $this->assertTrue($ws->isPayg());
        $this->assertNull($ws->service_until);
    }

    /** Pindah paket ke PAYG atau coba lewat ganti slug menghasilkan keadaan setengah jadi. */
    public function test_pindah_paket_hanya_ke_paket_bulanan(): void
    {
        $this->beri();

        foreach (['coba', 'payg', 'enterprise'] as $slug) {
            $this->actingAs($this->admin)
                ->post(route('admin.workspaces.plan', $this->workspace->id), ['plan' => $slug])
                ->assertSessionHasErrors('plan');
        }

        $this->assertSame('prime', $this->workspace->fresh()->subscription->plan_slug);
    }

    /** Workspace yang sudah punya periode tetap bisa memakai tombol lama. */
    public function test_tombol_lama_tetap_jalan_untuk_workspace_berperiode(): void
    {
        $this->beri('prime');
        $akhir = $this->workspace->fresh()->subscription->current_period_end;

        $this->actingAs($this->admin)
            ->post(route('admin.workspaces.plan', $this->workspace->id), ['plan' => 'elite'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)
            ->post(route('admin.workspaces.extend', $this->workspace->id), ['hari' => 10])
            ->assertSessionHasNoErrors();

        $sub = $this->workspace->fresh()->subscription;
        $this->assertSame('elite', $sub->plan_slug);
        $this->assertTrue($sub->current_period_end->equalTo($akhir->copy()->addDays(10)));
    }
}
