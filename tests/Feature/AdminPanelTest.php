<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pelanggan;

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

        $this->pelanggan = User::create([
            'name' => 'Owner',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Toko Contoh',
            'slug' => 'toko-contoh',
            'owner_id' => $this->pelanggan->id,
            'owner_email' => $this->pelanggan->email,
            'max_sessions' => 1,
            'monthly_message_quota' => 3000,
        ]);

        $this->workspace->members()->attach($this->pelanggan->id, ['role' => 'owner']);
    }

    /**
     * 404, bukan 403. Halaman yang menjawab "dilarang" memberi tahu bahwa di
     * alamat itu ada sesuatu — dan alamatnya mudah ditebak.
     */
    public function test_bukan_super_admin_tidak_melihat_panel_sama_sekali(): void
    {
        foreach (['overview', 'workspaces', 'invoices', 'sessions', 'users'] as $halaman) {
            $this->actingAs($this->pelanggan)
                ->get(route("admin.{$halaman}"))
                ->assertNotFound();
        }
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('admin.overview'))->assertRedirect(route('login'));
    }

    public function test_super_admin_bisa_membuka_semua_halaman(): void
    {
        foreach (['overview', 'workspaces', 'invoices', 'sessions', 'users'] as $halaman) {
            $this->actingAs($this->admin)
                ->get(route("admin.{$halaman}"))
                ->assertOk();
        }
    }

    public function test_menandai_lunas_memperpanjang_langganan_dan_menerapkan_paket(): void
    {
        $invoice = app(SubscriptionService::class)
            ->issueInvoice($this->workspace, 'elite', 'monthly');

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.paid', $invoice->id), ['catatan' => 'mutasi BCA 14:32'])
            ->assertRedirect();

        $invoice->refresh();

        $this->assertSame('paid', $invoice->status);
        $this->assertSame($this->admin->id, $invoice->paid_by_user_id);
        $this->assertSame('mutasi BCA 14:32', $invoice->note);

        $workspace = $this->workspace->fresh();
        $this->assertSame('active', $workspace->subscription->status);
        $this->assertSame(2, $workspace->max_sessions);
    }

    public function test_tagihan_lunas_tidak_bisa_ditandai_lunas_dua_kali(): void
    {
        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        $this->actingAs($this->admin)->post(route('admin.invoices.paid', $invoice->id));

        $berakhirPertama = $this->workspace->fresh()->subscription->current_period_end;

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.paid', $invoice->id))
            ->assertRedirect();

        // Yang dijaga bukan pesan galatnya, tapi akibatnya: kalau penandaan
        // kedua lolos, satu pembayaran memberi dua bulan.
        $this->assertTrue($berakhirPertama->equalTo($this->workspace->fresh()->subscription->current_period_end));
    }

    /**
     * Bukti yang menempel pada tagihan yang telanjur ditutup tidak boleh hilang
     * dari layar admin.
     *
     * Ini pernah terjadi: pelanggan mengunggah bukti, mengira gagal karena tidak
     * ada tanda yang cukup jelas, lalu membatalkan tagihannya sendiri — dan
     * saringan bawaan admin waktu itu hanya menampilkan `pending`, jadi
     * pembayarannya tidak muncul di mana pun.
     */
    public function test_bukti_pada_tagihan_yang_ditutup_tetap_muncul_di_admin(): void
    {
        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        $invoice->forceFill([
            'proof_path' => 'bukti-bayar/contoh.jpg',
            'status' => 'canceled',
        ])->save();

        $this->actingAs($this->admin)
            ->get(route('admin.invoices'))
            ->assertOk()
            ->assertSee($invoice->number);
    }

    public function test_tagihan_yang_ditutup_masih_bisa_ditandai_lunas(): void
    {
        // Uangnya sudah masuk; menolak menandainya lunas berarti memaksa
        // pelanggan membayar dua kali atau admin mengarang tagihan baru.
        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'elite', 'monthly');
        $invoice->forceFill(['proof_path' => 'bukti-bayar/contoh.jpg', 'status' => 'canceled'])->save();

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.paid', $invoice->id), ['catatan' => 'mutasi masuk'])
            ->assertRedirect();

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('active', $this->workspace->fresh()->subscription->status);
    }

    /**
     * Bukti yang tidak bisa diterima harus punya jalan keluar.
     *
     * Tanpa ini, tangkapan layar terpotong atau nominal yang tidak cocok
     * berujung buntu: admin tidak bisa menandainya lunas dengan jujur, dan
     * pelanggan tidak bisa mengunggah ulang karena tagihannya sudah tertutup.
     */
    public function test_menolak_bukti_membuka_kembali_tagihannya(): void
    {
        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');
        $invoice->forceFill([
            'proof_path' => 'bukti-bayar/contoh.jpg',
            'status' => 'expired',
            'due_at' => now()->subDays(3),
        ])->save();

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.reject-proof', $invoice->id), [
                'alasan' => 'Nominal tidak cocok dengan mutasi.',
            ])
            ->assertRedirect();

        $invoice->refresh();

        $this->assertSame('pending', $invoice->status);
        $this->assertNull($invoice->proof_path);
        $this->assertSame('Nominal tidak cocok dengan mutasi.', $invoice->note);

        // Tenggat baru wajib: dikembalikan ke pending dengan due_at yang sudah
        // lewat berarti job harian menutupnya lagi keesokan paginya.
        $this->assertTrue($invoice->due_at->isFuture());
    }

    public function test_alasan_penolakan_bukti_wajib_diisi(): void
    {
        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');
        $invoice->forceFill(['proof_path' => 'bukti-bayar/contoh.jpg'])->save();

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.reject-proof', $invoice->id), ['alasan' => ''])
            ->assertSessionHasErrors('alasan');

        $this->assertNotNull($invoice->fresh()->proof_path);
    }

    public function test_admin_bisa_menangguhkan_dan_memulihkan(): void
    {
        app(SubscriptionService::class)->ensureFor($this->workspace);

        $this->actingAs($this->admin)
            ->post(route('admin.workspaces.suspend', $this->workspace->id))
            ->assertRedirect();

        $this->assertSame('suspended', $this->workspace->fresh()->subscription->status);
        $this->assertSame('suspended', $this->workspace->fresh()->status);

        $this->actingAs($this->admin)->post(route('admin.workspaces.suspend', $this->workspace->id));

        $this->assertSame('active', $this->workspace->fresh()->subscription->status);
        $this->assertSame('active', $this->workspace->fresh()->status);
    }

    public function test_perpanjangan_manual_menambah_hari_tanpa_tagihan(): void
    {
        // Langganan berbayar yang punya periode — tombol ini sengaja menolak
        // workspace coba gratis dan PAYG, yang tidak punya periode untuk ditambah.
        $subscription = app(SubscriptionService::class)->ensureFor($this->workspace);
        $subscription->forceFill([
            'plan_slug' => 'essentials',
            'status' => 'active',
            'current_period_end' => now()->addDays(2),
        ])->save();
        $this->workspace->forceFill(['plan_slug' => 'essentials'])->save();

        $this->actingAs($this->admin)
            ->post(route('admin.workspaces.extend', $this->workspace->id), ['hari' => 10])
            ->assertSessionHasNoErrors();

        $this->assertTrue(
            $subscription->fresh()->current_period_end->isSameDay(now()->addDays(12))
        );
        $this->assertSame(0, Invoice::count());
    }

    public function test_kelonggaran_batas_tidak_mengubah_paket(): void
    {
        app(SubscriptionService::class)->ensureFor($this->workspace);

        $this->actingAs($this->admin)
            ->post(route('admin.workspaces.limits', $this->workspace->id), [
                'max_sessions' => 5,
                'monthly_message_quota' => 0,
            ]);

        $workspace = $this->workspace->fresh();

        $this->assertSame(5, $workspace->max_sessions);
        $this->assertSame(0, $workspace->monthly_message_quota);

        // Satu pelanggan yang butuh kuota lebih tidak boleh mengubah apa yang
        // didapat seluruh pelanggan pada paket yang sama — paketnya tetap yang
        // tadi, hanya angkanya yang dilonggarkan untuk workspace ini saja.
        $this->assertSame(config('plans.free'), $workspace->subscription->plan_slug);
    }

    public function test_super_admin_tidak_bisa_mencabut_haknya_sendiri(): void
    {
        // Orang terakhir yang melakukannya mengunci panel dari luar, dan
        // memperbaikinya hanya bisa lewat akses langsung ke database produksi.
        $this->actingAs($this->admin)
            ->post(route('admin.users.super', $this->admin->id))
            ->assertSessionHasErrors('pengguna');

        $this->assertTrue($this->admin->fresh()->is_super_admin);
    }

    public function test_super_admin_bisa_mengangkat_orang_lain(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.super', $this->pelanggan->id))
            ->assertRedirect();

        $this->assertTrue($this->pelanggan->fresh()->is_super_admin);
    }

    public function test_pelanggan_tidak_bisa_menandai_tagihannya_sendiri_lunas(): void
    {
        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'elite', 'yearly');

        $this->actingAs($this->pelanggan)
            ->post(route('admin.invoices.paid', $invoice->id))
            ->assertNotFound();

        $this->assertSame('pending', $invoice->fresh()->status);
    }
}
