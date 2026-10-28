<?php

namespace Tests\Feature;

use App\Jobs\BillingCycleJob;
use App\Models\ReferralCode;
use App\Models\ReferralRedemption;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\ReferralService;
use App\Services\Billing\SubscriptionService;
use App\Support\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * Kode referal: diskon pembeli dan komisi reseller.
 *
 * Yang diuji di sini terutama **penolakannya**, bukan jalur bahagianya. Tiap
 * penolakan menutup satu cara program ini kehilangan uang tanpa ada yang
 * menyadarinya: kode sendiri yang berubah jadi potongan harga pribadi, diskon
 * yang menempel selamanya alih-alih sekali, dan jatah reseller yang habis oleh
 * tagihan yang tidak pernah dibayar.
 */
class ReferralTest extends TestCase
{
    use RefreshDatabase;

    private User $reseller;

    private User $pembeli;

    private Workspace $workspace;

    private ReferralCode $kode;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        Mail::fake();

        $this->reseller = User::create([
            'name' => 'Reseller',
            'email' => 'reseller@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->pembeli = User::create([
            'name' => 'Pembeli',
            'email' => 'pembeli@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Toko Pembeli',
            'slug' => 'toko-pembeli',
            'owner_id' => $this->pembeli->id,
            'owner_email' => $this->pembeli->email,
            'billing_phone' => '6281234567890',
            'max_sessions' => 1,
            'monthly_message_quota' => 1000,
        ]);

        $this->workspace->members()->attach($this->pembeli->id, ['role' => 'owner']);

        $this->kode = ReferralCode::create([
            'code' => ReferralCode::buatKode(),
            'owner_user_id' => $this->reseller->id,
            'discount_percent' => 10,
            'commission_percent' => 20,
        ]);
    }

    // ===================== Bentuk kode =====================

    public function test_kode_lima_huruf_tanpa_i_dan_o(): void
    {
        foreach (range(1, 200) as $ke) {
            $kode = ReferralCode::buatKode();

            $this->assertSame(5, strlen($kode));
            $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z]{5}$/', $kode);
        }
    }

    public function test_kode_tidak_pernah_bertabrakan(): void
    {
        // Tabrakan diperiksa saat pembuatan, bukan diharapkan tidak terjadi:
        // kolomnya unik, dan tabrakan yang lolos gagal dengan galat 1062 di
        // depan admin yang tidak melakukan apa pun yang salah.
        $dibuat = [];

        foreach (range(1, 50) as $ke) {
            $user = User::create([
                'name' => "User {$ke}",
                'email' => "user{$ke}@contoh.id",
                'password' => Hash::make('secret'),
            ]);
            $kode = ReferralCode::buatKode();
            ReferralCode::create([
                'code' => $kode,
                'owner_user_id' => $user->id,
                'discount_percent' => 5,
                'commission_percent' => 5,
            ]);
            $dibuat[] = $kode;
        }

        $this->assertCount(50, array_unique($dibuat));
    }

    public function test_huruf_kecil_dan_spasi_dimaafkan(): void
    {
        $referral = app(ReferralService::class)->periksa(
            ' '.strtolower($this->kode->code).' ',
            $this->workspace,
        );

        $this->assertSame($this->kode->id, $referral->id);
    }

    // ===================== Penolakan =====================

    public function test_kode_sendiri_ditolak(): void
    {
        $milikSendiri = Workspace::create([
            'name' => 'Workspace Reseller',
            'slug' => 'workspace-reseller',
            'owner_id' => $this->reseller->id,
            'owner_email' => $this->reseller->email,
            'max_sessions' => 1,
            'monthly_message_quota' => 1000,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('kode referal milik Anda sendiri');

        app(ReferralService::class)->periksa($this->kode->code, $milikSendiri);
    }

    public function test_kode_kedua_di_workspace_yang_sama_ditolak(): void
    {
        $resellerKedua = User::create([
            'name' => 'Reseller Kedua',
            'email' => 'reseller2@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $kedua = ReferralCode::create([
            'code' => ReferralCode::buatKode(),
            'owner_user_id' => $resellerKedua->id,
            'discount_percent' => 50,
            'commission_percent' => 5,
        ]);

        app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly', $this->kode);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sudah pernah memakai kode referal');

        app(ReferralService::class)->periksa($kedua->code, $this->workspace);
    }

    /**
     * Kalau diskon berlaku terus, satu kode memotong seluruh pendapatan dari
     * pelanggan itu selamanya — reseller dibayar sekali untuk mendatangkan
     * orang, tapi potongannya menempel tiap bulan sampai pelanggan itu berhenti.
     */
    public function test_diskon_hanya_untuk_tagihan_pertama(): void
    {
        $subscriptions = app(SubscriptionService::class);
        $pertama = $subscriptions->issueInvoice($this->workspace, 'prime', 'monthly');
        $subscriptions->markPaid($pertama);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tagihan pertama');

        app(ReferralService::class)->periksa($this->kode->code, $this->workspace->fresh());
    }

    public function test_kode_kedaluwarsa_ditolak(): void
    {
        $this->kode->forceFill(['expires_at' => now()->subDay()])->save();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('kedaluwarsa');

        app(ReferralService::class)->periksa($this->kode->code, $this->workspace);
    }

    public function test_kode_yang_dimatikan_ditolak(): void
    {
        $this->kode->forceFill(['is_active' => false])->save();

        $this->expectException(RuntimeException::class);
        app(ReferralService::class)->periksa($this->kode->code, $this->workspace);
    }

    public function test_kode_yang_jatahnya_habis_ditolak(): void
    {
        $this->kode->forceFill(['max_redemptions' => 2, 'redeemed_count' => 2])->save();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('batas pemakaiannya');

        app(ReferralService::class)->periksa($this->kode->code, $this->workspace);
    }

    public function test_kode_tak_dikenal_ditolak(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tidak dikenali');

        app(ReferralService::class)->periksa('ZZZZZ', $this->workspace);
    }

    // ===================== Nominal =====================

    /**
     * Promo perkenalan dan kode referal DITUMPUK, dan urutannya menentukan.
     *
     * Promo dipotong lebih dulu, lalu kode referal memotong SISANYA — bukan
     * keduanya dari harga normal. Kalau keduanya diambil dari harga normal,
     * potongan 47% dan 10% berjumlah 57%, sementara yang dimaksud adalah 10%
     * dari yang tersisa sesudah promo. Selisihnya uang sungguhan.
     */
    public function test_promo_perkenalan_dan_referal_ditumpuk_dengan_urutan_yang_benar(): void
    {
        $invoice = app(SubscriptionService::class)
            ->issueInvoice($this->workspace, 'prime', 'monthly', $this->kode);

        $plan = Plan::get('prime');
        $harga = $plan->price('monthly');
        $pajak = (int) round($harga * config('billing.tax_percent') / 100);

        $intro = $harga - $plan->introPrice('monthly');
        $referal = (int) floor(($harga + $pajak - $intro) * 10 / 100);

        $this->assertSame($intro, (int) $invoice->intro_discount_amount);
        $this->assertSame($referal, $invoice->referralDiscount());
        $this->assertSame($intro + $referal, (int) $invoice->discount_amount);
        $this->assertSame($this->kode->id, $invoice->referral_code_id);

        // Inti tes ini: total = harga + pajak − seluruh potongan + kode unik.
        // Kalau potongan diambil SETELAH kode unik dihitung, angka yang
        // ditransfer pelanggan tidak akan sama dengan yang tertulis di
        // tagihannya.
        $this->assertSame(
            $harga + $pajak - $intro - $referal + $invoice->unique_code,
            $invoice->total,
        );

        // Referal dihitung dari sisa sesudah promo, BUKAN dari harga normal.
        $this->assertLessThan(
            (int) floor(($harga + $pajak) * 10 / 100),
            $referal,
            'Potongan referal dihitung dari harga normal, bukan dari sisa sesudah promo.',
        );
    }

    /** Promo hanya sekali: perpanjangan berikutnya kembali harga normal. */
    public function test_promo_perkenalan_hanya_untuk_pembelian_pertama(): void
    {
        $subscriptions = app(SubscriptionService::class);

        $pertama = $subscriptions->issueInvoice($this->workspace, 'prime', 'monthly');
        $this->assertGreaterThan(0, (int) $pertama->intro_discount_amount);

        $subscriptions->markPaid($pertama);

        $kedua = $subscriptions->issueInvoice($this->workspace->fresh(), 'elite', 'monthly');

        $this->assertSame(0, (int) $kedua->intro_discount_amount);
        $this->assertSame(Plan::get('elite')->price('monthly'), $kedua->amount);
    }

    /**
     * Kode unik menjamin nominal AKHIR unik di antara tagihan terbuka.
     *
     * Sejak ada diskon, nominal akhir bukan lagi `amount + tax_amount` — dan
     * kalau kueri pemilihnya tidak ikut mengurangi diskon, tagihan berdiskon
     * dan tagihan tanpa diskon bisa berakhir dengan nominal yang persis sama.
     * Dua uang masuk yang identik adalah persis keadaan yang mekanisme ini ada
     * untuk mencegahnya.
     */
    public function test_nominal_akhir_tidak_bertabrakan_antar_tagihan_terbuka(): void
    {
        $subscriptions = app(SubscriptionService::class);
        $total = [];

        foreach (range(1, 8) as $ke) {
            $ws = Workspace::create([
                'name' => "Toko {$ke}",
                'slug' => "toko-{$ke}",
                'owner_id' => $this->pembeli->id,
                'owner_email' => $this->pembeli->email,
                'max_sessions' => 1,
                'monthly_message_quota' => 1000,
            ]);

            // Berselang-seling: separuh berdiskon, separuh tidak. Keduanya
            // harus tetap saling unik.
            $total[] = $subscriptions->issueInvoice(
                $ws,
                'prime',
                'monthly',
                $ke % 2 === 0 ? $this->kode : null,
            )->total;
        }

        $this->assertCount(8, array_unique($total), 'Ada dua tagihan terbuka dengan nominal identik.');
    }

    // ===================== Komisi =====================

    public function test_komisi_baru_disetujui_setelah_lunas(): void
    {
        $subscriptions = app(SubscriptionService::class);
        $invoice = $subscriptions->issueInvoice($this->workspace, 'prime', 'monthly', $this->kode);

        $redemption = ReferralRedemption::where('invoice_id', $invoice->id)->firstOrFail();

        $this->assertSame('pending', $redemption->status);
        $this->assertSame(0, $this->kode->fresh()->redeemed_count, 'Jatah kode berkurang sebelum dibayar.');

        // Komisi dari total SETELAH diskon, bukan dari harga penuh.
        $this->assertSame((int) floor($invoice->total * 20 / 100), $redemption->commission_amount);

        $subscriptions->markPaid($invoice);

        $this->assertSame('approved', $redemption->fresh()->status);
        $this->assertSame(1, $this->kode->fresh()->redeemed_count);
    }

    /**
     * Kode yang ditukar tapi tagihannya tidak pernah dibayar tidak boleh
     * menghabiskan jatah `max_redemptions`. Kalau bisa, siapa pun menghabiskan
     * jatah sebuah kode dengan menerbitkan tagihan berulang tanpa membayarnya —
     * dan resellernya kehilangan jatah tanpa menerima satu rupiah pun.
     */
    public function test_tagihan_kedaluwarsa_tidak_menghasilkan_komisi(): void
    {
        $invoice = app(SubscriptionService::class)
            ->issueInvoice($this->workspace, 'prime', 'monthly', $this->kode);

        $invoice->forceFill(['due_at' => now()->subDay()])->save();

        app()->call([new BillingCycleJob, 'handle']);

        $this->assertSame('expired', $invoice->fresh()->status);
        $this->assertSame('void', ReferralRedemption::where('invoice_id', $invoice->id)->first()->status);
        $this->assertSame(0, $this->kode->fresh()->redeemed_count);
        $this->assertSame(0, $this->kode->fresh()->komisiTerutang());
    }

    public function test_tagihan_dibatalkan_tidak_menghasilkan_komisi(): void
    {
        $invoice = app(SubscriptionService::class)
            ->issueInvoice($this->workspace, 'prime', 'monthly', $this->kode);

        $this->actingAs($this->pembeli)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('billing.invoice.cancel', $invoice->id));

        $this->assertSame('canceled', $invoice->fresh()->status);
        $this->assertSame('void', ReferralRedemption::where('invoice_id', $invoice->id)->first()->status);
        $this->assertSame(0, $this->kode->fresh()->redeemed_count);
    }

    /**
     * `markPaid()` yang berjalan dua kali tidak boleh menaikkan jatah dua kali.
     * Panel admin memang mengizinkan menandai lunas tagihan yang sudah ditutup,
     * jadi jalur ini bukan hipotesis.
     */
    public function test_menandai_lunas_dua_kali_tidak_menggandakan_jatah(): void
    {
        $subscriptions = app(SubscriptionService::class);
        $invoice = $subscriptions->issueInvoice($this->workspace, 'prime', 'monthly', $this->kode);

        $subscriptions->markPaid($invoice);
        $subscriptions->markPaid($invoice->fresh());

        $this->assertSame(1, $this->kode->fresh()->redeemed_count);
    }

    // ===================== Antarmuka =====================

    public function test_halaman_bayar_menampilkan_potongan_sebelum_tagihan_terbit(): void
    {
        $jawaban = $this->actingAs($this->pembeli)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->postJson(route('billing.referral.review'), [
                'code' => $this->kode->code,
                'plan' => 'prime',
                'period' => 'monthly',
            ]);

        $harga = Plan::get('prime')->price('monthly');

        $jawaban->assertOk()
            ->assertJson(['sah' => true, 'persen' => 10, 'diskon' => (int) floor($harga * 10 / 100)]);

        // Tidak satu pun tagihan terbit hanya karena kodenya diperiksa.
        $this->assertSame(0, $this->workspace->invoices()->count());
    }

    public function test_kode_ditolak_dijawab_dengan_alasannya_bukan_galat(): void
    {
        $this->kode->forceFill(['is_active' => false])->save();

        $this->actingAs($this->pembeli)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->postJson(route('billing.referral.review'), [
                'code' => $this->kode->code,
                'plan' => 'prime',
                'period' => 'monthly',
            ])
            ->assertOk()
            ->assertJson(['sah' => false]);
    }

    public function test_checkout_dengan_kode_menerbitkan_tagihan_berdiskon(): void
    {
        $this->actingAs($this->pembeli)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('billing.checkout'), [
                'plan' => 'prime',
                'period' => 'monthly',
                'referral' => $this->kode->code,
            ])->assertRedirect();

        $invoice = $this->workspace->invoices()->latest()->first();

        $this->assertGreaterThan(0, $invoice->discount_amount);
        $this->assertSame($this->kode->id, $invoice->referral_code_id);
    }

    public function test_checkout_dengan_kode_salah_tidak_menerbitkan_tagihan(): void
    {
        $this->actingAs($this->pembeli)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->post(route('billing.checkout'), [
                'plan' => 'prime',
                'period' => 'monthly',
                'referral' => 'ZZZZZ',
            ])->assertSessionHasErrors('referral');

        $this->assertSame(0, $this->workspace->invoices()->count());
    }

    public function test_admin_bisa_membuat_kode_dan_menandai_komisi_dibayar(): void
    {
        $admin = User::create([
            'name' => 'Pesanvo Finance',
            'email' => 'finance@pesanvo.id',
            'password' => Hash::make('rahasia12345'),
            'is_super_admin' => true,
        ]);

        $resellerBaru = User::create([
            'name' => 'Reseller Baru',
            'email' => 'reseller.baru@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->actingAs($admin)->post(route('admin.referrals.store'), [
            'owner_user_id' => $resellerBaru->id,
            'discount_percent' => 15,
            'commission_percent' => 25,
        ])->assertRedirect();

        $baru = ReferralCode::latest('id')->first();
        $this->assertSame(15, $baru->discount_percent);
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z]{5}$/', $baru->code);

        // Komisi yang belum disetujui tidak bisa ditandai dibayar.
        $subscriptions = app(SubscriptionService::class);
        $invoice = $subscriptions->issueInvoice($this->workspace, 'prime', 'monthly', $this->kode);
        $redemption = ReferralRedemption::where('invoice_id', $invoice->id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.referrals.commission.paid', $redemption->id))
            ->assertSessionHasErrors('komisi');

        $subscriptions->markPaid($invoice);

        $this->actingAs($admin)
            ->post(route('admin.referrals.commission.paid', $redemption->id))
            ->assertRedirect();

        $this->assertSame('paid', $redemption->fresh()->status);
    }

    public function test_satu_akun_hanya_bisa_memiliki_satu_kode_referal_selamanya(): void
    {
        $admin = User::create([
            'name' => 'Pesanvo Admin',
            'email' => 'admin.unique@pesanvo.id',
            'password' => Hash::make('rahasia12345'),
            'is_super_admin' => true,
        ]);

        // Percobaan membuat kode kedua untuk reseller yang sudah punya kode harus ditolak
        $this->actingAs($admin)->post(route('admin.referrals.store'), [
            'owner_user_id' => $this->reseller->id,
            'discount_percent' => 10,
            'commission_percent' => 20,
        ])->assertSessionHasErrors('owner_user_id');

        $this->assertSame(1, ReferralCode::where('owner_user_id', $this->reseller->id)->count());
    }

    public function test_halaman_admin_reseller_terbuka(): void
    {
        $admin = User::create([
            'name' => 'Pesanvo Finance',
            'email' => 'finance@pesanvo.id',
            'password' => Hash::make('rahasia12345'),
            'is_super_admin' => true,
        ]);

        $this->actingAs($admin)->get(route('admin.referrals'))
            ->assertOk()
            ->assertSee($this->kode->code);
    }
}
