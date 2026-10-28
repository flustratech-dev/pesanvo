<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\SubscriptionService;
use App\Support\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Harga perkenalan: sekali seumur workspace, tidak pernah dua kali.
 *
 * Yang paling mahal kalau rusak bukan promonya hilang, melainkan promonya
 * berlaku terus — satu workspace membayar separuh harga selamanya tanpa ada
 * yang menyadarinya, karena tidak ada satu pun galat yang muncul saat itu
 * terjadi. Angkanya baru terlihat saat seseorang menjumlahkan pendapatan.
 */
class HargaPerkenalanTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $pemilik;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        Mail::fake();

        $this->pemilik = User::create([
            'name' => 'Owner',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Toko Baru',
            'slug' => 'toko-baru',
            'owner_id' => $this->pemilik->id,
            'owner_email' => $this->pemilik->email,
            'billing_phone' => '6281234567890',
            'max_sessions' => 1,
            'monthly_message_quota' => 1000,
        ]);

        $this->workspace->members()->attach($this->pemilik->id, ['role' => 'owner']);
    }

    // ===================== Angka di config =====================

    /**
     * Promo tidak boleh lebih mahal dari harga normal.
     *
     * Salah ketik satu digit di config akan diam-diam menagih LEBIH banyak dari
     * harga yang dipajang — dan menagih lebih adalah kegagalan yang jauh lebih
     * buruk daripada kehilangan promonya.
     */
    public function test_promo_tidak_pernah_lebih_mahal_dari_harga_normal(): void
    {
        foreach (Plan::all() as $plan) {
            foreach (['monthly', 'yearly'] as $periode) {
                $promo = $plan->introPrice($periode);

                if ($promo !== null) {
                    $this->assertLessThan(
                        $plan->price($periode),
                        $promo,
                        "Promo {$plan->slug} {$periode} tidak lebih murah dari harga normal.",
                    );
                    $this->assertGreaterThan(0, $promo, "Promo {$plan->slug} {$periode} nol atau minus.");
                }
            }
        }
    }

    public function test_ketiga_paket_berbayar_punya_harga_perkenalan(): void
    {
        foreach (['essentials', 'prime', 'elite'] as $slug) {
            $this->assertTrue(Plan::get($slug)->hasIntro('monthly'), "{$slug} tidak punya promo bulanan.");
            $this->assertTrue(Plan::get($slug)->hasIntro('yearly'), "{$slug} tidak punya promo tahunan.");
        }
    }

    /** Paket gratis dan PAYG tidak punya harga bulanan, jadi tidak punya promo. */
    public function test_paket_coba_dan_payg_tanpa_promo(): void
    {
        foreach (['coba', 'payg'] as $slug) {
            $this->assertFalse(Plan::get($slug)->hasIntro('monthly'));
            $this->assertFalse(Plan::get($slug)->hasIntro('yearly'));
        }
    }

    // ===================== Kelayakan =====================

    public function test_tagihan_pertama_mendapat_harga_perkenalan(): void
    {
        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'essentials', 'monthly');

        $plan = Plan::get('essentials');

        $this->assertSame(
            $plan->price('monthly') - $plan->introPrice('monthly'),
            (int) $invoice->intro_discount_amount,
        );
        $this->assertSame($plan->introPrice('monthly') + $invoice->unique_code, $invoice->total);
    }

    public function test_berlaku_untuk_tahunan_juga(): void
    {
        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'essentials', 'yearly');

        $plan = Plan::get('essentials');

        $this->assertSame(
            $plan->price('yearly') - $plan->introPrice('yearly'),
            (int) $invoice->intro_discount_amount,
        );
    }

    /**
     * Inti seluruh berkas ini.
     *
     * Kalau ini rusak, satu workspace membayar separuh harga selamanya tanpa
     * satu pun galat yang muncul.
     */
    public function test_perpanjangan_kembali_ke_harga_normal(): void
    {
        $subscriptions = app(SubscriptionService::class);

        $pertama = $subscriptions->issueInvoice($this->workspace, 'essentials', 'monthly');
        $subscriptions->markPaid($pertama);

        $kedua = $subscriptions->issueInvoice($this->workspace->fresh(), 'essentials', 'monthly');

        $this->assertSame(0, (int) $kedua->intro_discount_amount);
        $this->assertSame(
            Plan::get('essentials')->price('monthly') + $kedua->unique_code,
            $kedua->total,
        );
    }

    /** Pindah paket sesudah pernah membayar juga tidak mengembalikan promo. */
    public function test_pindah_paket_setelah_bayar_tidak_mengembalikan_promo(): void
    {
        $subscriptions = app(SubscriptionService::class);

        $subscriptions->markPaid($subscriptions->issueInvoice($this->workspace, 'essentials', 'monthly'));

        $naik = $subscriptions->issueInvoice($this->workspace->fresh(), 'elite', 'yearly');

        $this->assertSame(0, (int) $naik->intro_discount_amount);
    }

    /**
     * Tagihan yang terbit tapi TIDAK dibayar tidak menghabiskan promo.
     *
     * Kalau kelayakannya dihitung dari "pernah ada tagihan", pelanggan yang
     * membatalkan tagihan pertamanya lalu memilih paket lain akan membayar
     * harga penuh — dihukum karena berubah pikiran sebelum membayar sepeser pun.
     */
    public function test_tagihan_yang_belum_dibayar_tidak_menghabiskan_promo(): void
    {
        $subscriptions = app(SubscriptionService::class);

        $subscriptions->issueInvoice($this->workspace, 'essentials', 'monthly');

        $lain = $subscriptions->issueInvoice($this->workspace->fresh(), 'prime', 'monthly');

        $this->assertGreaterThan(0, (int) $lain->intro_discount_amount);
    }

    /**
     * Masa coba gratis bukan "pernah membayar".
     *
     * Seluruh workspace baru lahir di paket coba; kalau itu dihitung sebagai
     * pembelian, tidak ada satu pun pelanggan yang pernah mendapat promo.
     */
    public function test_paket_coba_gratis_tidak_menghabiskan_promo(): void
    {
        // Paket coba diberikan `ensureFor()`, bukan oleh kolom bawaan — jadi
        // ia harus benar-benar dipanggil supaya yang diuji keadaan sungguhan.
        app(SubscriptionService::class)->ensureFor($this->workspace);
        $this->workspace->refresh();

        $this->assertTrue($this->workspace->isFreeTier());
        $this->assertTrue($this->workspace->belumPernahBayar());
        $this->assertTrue($this->workspace->berhakHargaPerkenalan('prime', 'monthly'));
    }

    // ===================== Antarmuka =====================

    public function test_halaman_harga_menampilkan_promo_dan_menyebut_sekali(): void
    {
        $this->actingAs($this->pemilik)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('billing.plans'))
            ->assertOk()
            ->assertSee('Harga pembelian pertama')
            ->assertSee((string) Plan::get('essentials')->introPrice('monthly'), false);
    }

    /**
     * Yang sudah pernah membayar tidak boleh melihat harga promo.
     *
     * Menampilkannya adalah janji harga yang akan dibatalkan sendiri saat
     * tagihannya terbit — dan yang membacanya baru tahu setelah melihat nominal
     * transfernya.
     */
    public function test_yang_sudah_pernah_bayar_tidak_ditawari_promo_lagi(): void
    {
        $subscriptions = app(SubscriptionService::class);
        $subscriptions->markPaid($subscriptions->issueInvoice($this->workspace, 'essentials', 'monthly'));

        $this->actingAs($this->pemilik)
            ->withSession(['current_workspace_id' => $this->workspace->fresh()->id])
            ->get(route('billing.plans'))
            ->assertOk()
            ->assertDontSee('Harga pembelian pertama');
    }

    public function test_halaman_depan_menampilkan_promo(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Harga pembelian pertama')
            ->assertSee('Perpanjangan berikutnya');
    }
}
