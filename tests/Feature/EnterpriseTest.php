<?php

namespace Tests\Feature;

use App\Models\EnterpriseLead;
use App\Models\EnterprisePlan;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\SubscriptionService;
use App\Services\Notifications\WhatsAppNotifier;
use App\Support\PerkiraanEnterprise;
use App\Support\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Paket Enterprise: permintaan penawaran, kesepakatan, dan tagihannya.
 *
 * Yang paling mahal kalau rusak: pelanggan membayar harga Enterprise lalu
 * menerima batas paket termurah. Batas Enterprise tidak ada di
 * `config/plans.php` — angka di sana sengaja cuma cadangan — jadi
 * `applyPlanLimits()` harus mengambilnya dari baris kesepakatan. Kalau ia
 * lupa, tidak ada satu pun galat yang muncul; yang muncul cuma pelanggan yang
 * nomor keduanya gagal tersambung.
 */
class EnterpriseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        Mail::fake();

        $this->admin = User::create([
            'name' => 'Pesanvo Finance',
            'email' => 'finance@pesanvo.id',
            'password' => Hash::make('rahasia12345'),
            'is_super_admin' => true,
        ]);

        $pemilik = User::create([
            'name' => 'Owner',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->workspace = Workspace::create([
            'name' => 'PT Besar',
            'slug' => 'pt-besar',
            'owner_id' => $pemilik->id,
            'owner_email' => $pemilik->email,
            'billing_email' => 'keuangan@contoh.id',
            'max_sessions' => 1,
            'monthly_message_quota' => 1000,
        ]);

        $this->workspace->members()->attach($pemilik->id, ['role' => 'owner']);
    }

    private function isiForm(array $ganti = []): TestResponse
    {
        return $this->post(route('enterprise.contact'), $ganti + [
            'name' => 'Budi Santoso',
            'company' => 'PT Contoh Nusantara',
            'email' => 'budi@contoh.co.id',
            'phone' => '081234567890',
            'estimated_sessions' => 3,
            'estimated_messages' => 200000,
            'needs' => 'Lima cabang, tersambung ke ERP kami.',
        ]);
    }

    private function buatKesepakatan(EnterpriseLead $lead, array $ganti = []): EnterprisePlan
    {
        $this->actingAs($this->admin)->post(route('admin.enterprise.plan.store', $lead->id), $ganti + [
            'workspace_id' => $this->workspace->id,
            'name' => 'Enterprise PT Besar',
            'price_monthly' => 2_500_000,
            'price_yearly' => 25_000_000,
            'max_sessions' => 3,
            'monthly_message_quota' => 500_000,
            'max_api_keys' => 0,
            'max_members' => 0,
            'message_retention_days' => 730,
            'api_rate_limit_per_minute' => 600,
        ]);

        return EnterprisePlan::latest('id')->firstOrFail();
    }

    // ===================== Form publik =====================

    /**
     * Terbuka untuk tamu, dan itu disengaja.
     *
     * Yang paling sering butuh lebih dari Elite adalah orang yang sedang
     * menimbang apakah produk ini sanggup, bukan pelanggan yang sudah masuk.
     */
    public function test_tamu_tanpa_akun_bisa_mengirim_permintaan(): void
    {
        $this->isiForm()->assertRedirect();

        $lead = EnterpriseLead::firstOrFail();

        $this->assertSame('baru', $lead->status);
        $this->assertNull($lead->user_id);
        $this->assertSame('PT Contoh Nusantara', $lead->company);
    }

    public function test_permintaan_tanpa_kontak_ditolak(): void
    {
        $this->post(route('enterprise.contact'), ['name' => 'Tanpa Kontak'])
            ->assertSessionHasErrors(['email', 'phone']);

        $this->assertSame(0, EnterpriseLead::count());
    }

    /** Angka perkiraan opsional: form yang menolak orang karena itu kehilangan lead. */
    public function test_perkiraan_boleh_dikosongkan(): void
    {
        $this->isiForm(['estimated_sessions' => null, 'estimated_messages' => null, 'needs' => null])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, EnterpriseLead::count());
    }

    /**
     * Form publik tanpa batas adalah undangan bagi bot untuk mengisi tabelnya
     * sampai permintaan sungguhan tidak bisa ditemukan lagi.
     */
    public function test_form_publik_dibatasi_rate_limit(): void
    {
        foreach (range(1, 3) as $ke) {
            $this->isiForm()->assertRedirect();
        }

        $this->isiForm()->assertStatus(429);
    }

    public function test_tim_dikabari_lewat_whatsapp_tanpa_membawa_isi_kebutuhan(): void
    {
        $rahasia = 'Nama klien kami PT Rahasia, omzet 4 miliar';

        $this->mock(WhatsAppNotifier::class, function ($mock) use ($rahasia) {
            $mock->shouldReceive('toAdmin')
                ->once()
                ->withArgs(fn ($pesan, $sekali = null) => ! str_contains($pesan, $rahasia));
        });

        $this->isiForm(['needs' => $rahasia]);
    }

    // ===================== Panel admin =====================

    public function test_halaman_admin_terbuka_dan_saringan_bawaan_baru(): void
    {
        $this->isiForm();
        $lead = EnterpriseLead::firstOrFail();

        $this->actingAs($this->admin)->get(route('admin.enterprise'))
            ->assertOk()
            ->assertSee('Budi Santoso');

        $this->actingAs($this->admin)->get(route('admin.enterprise.show', $lead->id))
            ->assertOk()
            ->assertSee('PT Contoh Nusantara');
    }

    public function test_permintaan_yang_sudah_dijawab_hilang_dari_saringan_bawaan(): void
    {
        $this->isiForm();
        $lead = EnterpriseLead::firstOrFail();

        $this->actingAs($this->admin)->post(route('admin.enterprise.status', $lead->id), [
            'status' => 'selesai',
            'admin_note' => 'Sudah ditelepon, ambil Elite saja.',
        ])->assertRedirect();

        $this->assertSame('selesai', $lead->fresh()->status);

        $this->actingAs($this->admin)->get(route('admin.enterprise'))
            ->assertOk()
            ->assertDontSee('Budi Santoso');
    }

    public function test_menyusun_kesepakatan_memindahkan_permintaan_ke_diproses(): void
    {
        $this->isiForm();
        $lead = EnterpriseLead::firstOrFail();

        $plan = $this->buatKesepakatan($lead);

        $this->assertSame('diproses', $lead->fresh()->status);
        $this->assertSame($this->workspace->id, $plan->workspace_id);
        $this->assertTrue($plan->is_active);
    }

    /**
     * Menjanjikan lebih banyak nomor dari kapasitas engine berarti menjual
     * sesuatu yang belum ada — dan yang menemukan kekurangannya adalah
     * pelanggan yang sudah membayar mahal.
     */
    public function test_nomor_melebihi_kapasitas_engine_ditolak(): void
    {
        config(['gateway.engine.max_sessions' => 3]);

        $this->isiForm();
        $lead = EnterpriseLead::firstOrFail();

        $this->actingAs($this->admin)->post(route('admin.enterprise.plan.store', $lead->id), [
            'workspace_id' => $this->workspace->id,
            'name' => 'Terlalu Besar',
            'price_monthly' => 5_000_000,
            'price_yearly' => 50_000_000,
            'max_sessions' => 50,
            'monthly_message_quota' => 0,
            'max_api_keys' => 0,
            'max_members' => 0,
            'message_retention_days' => 365,
            'api_rate_limit_per_minute' => 600,
        ])->assertSessionHasErrors('max_sessions');

        $this->assertSame(0, EnterprisePlan::count());
    }

    /** Kesepakatan lama dimatikan, bukan dihapus. */
    public function test_kesepakatan_baru_mematikan_yang_lama_tanpa_menghapusnya(): void
    {
        $this->isiForm();
        $lead = EnterpriseLead::firstOrFail();

        $lama = $this->buatKesepakatan($lead);
        $baru = $this->buatKesepakatan($lead, ['name' => 'Enterprise PT Besar 2027', 'price_monthly' => 3_000_000]);

        $this->assertFalse($lama->fresh()->is_active);
        $this->assertTrue($baru->fresh()->is_active);
        $this->assertSame(2, EnterprisePlan::count(), 'Kesepakatan lama seharusnya disimpan sebagai riwayat.');
        $this->assertSame($baru->id, EnterprisePlan::berlakuUntuk($this->workspace->id)->id);
    }

    // ===================== Tagihan dan batas =====================

    public function test_tagihan_enterprise_memakai_harga_kesepakatan(): void
    {
        $this->isiForm();
        $lead = EnterpriseLead::firstOrFail();
        $plan = $this->buatKesepakatan($lead);

        $this->actingAs($this->admin)
            ->post(route('admin.enterprise.plan.invoice', [$lead->id, $plan->id]), ['period' => 'yearly'])
            ->assertRedirect();

        $invoice = $this->workspace->invoices()->latest('id')->firstOrFail();

        $this->assertSame('enterprise', $invoice->plan_slug);
        $this->assertSame(25_000_000, $invoice->amount);
        $this->assertSame(25_000_000 + $invoice->unique_code, $invoice->total);
    }

    /**
     * Harga Enterprise sudah hasil negosiasi. Memotongnya lagi dengan promo
     * perkenalan berarti angka yang disepakati bukan angka yang ditagihkan.
     */
    public function test_tagihan_enterprise_tidak_kena_promo_perkenalan(): void
    {
        $this->isiForm();
        $lead = EnterpriseLead::firstOrFail();
        $plan = $this->buatKesepakatan($lead);

        $this->assertTrue($this->workspace->belumPernahBayar());

        $invoice = app(SubscriptionService::class)->issueEnterpriseInvoice($plan, 'monthly');

        $this->assertSame(0, (int) $invoice->intro_discount_amount);
        $this->assertSame(0, (int) $invoice->discount_amount);
    }

    /**
     * Inti seluruh berkas ini.
     *
     * Batas Enterprise datang dari kesepakatan, bukan dari katalog — dan kalau
     * ini rusak, pelanggan membayar Rp 2,5 juta lalu menerima batas satu nomor.
     */
    public function test_batas_kesepakatan_tersalin_ke_workspace_saat_lunas(): void
    {
        $this->isiForm();
        $lead = EnterpriseLead::firstOrFail();
        $plan = $this->buatKesepakatan($lead);

        $subscriptions = app(SubscriptionService::class);
        $invoice = $subscriptions->issueEnterpriseInvoice($plan, 'monthly');

        // Belum lunas: batasnya belum berubah.
        $this->assertSame(1, (int) $this->workspace->fresh()->max_sessions);

        $subscriptions->markPaid($invoice);

        $ws = $this->workspace->fresh();

        $this->assertSame('enterprise', $ws->plan_slug);
        $this->assertSame(3, (int) $ws->max_sessions);
        $this->assertSame(500_000, (int) $ws->monthly_message_quota);
        $this->assertSame(600, (int) $ws->api_rate_limit_per_minute);
        $this->assertTrue($ws->isActive());
    }

    /** Mematikan kesepakatan tidak mencabut periode yang sudah dibayar. */
    public function test_mematikan_kesepakatan_tidak_mencabut_batas_yang_sudah_dibayar(): void
    {
        $this->isiForm();
        $lead = EnterpriseLead::firstOrFail();
        $plan = $this->buatKesepakatan($lead);

        $subscriptions = app(SubscriptionService::class);
        $subscriptions->markPaid($subscriptions->issueEnterpriseInvoice($plan, 'monthly'));

        $this->actingAs($this->admin)
            ->post(route('admin.enterprise.plan.deactivate', [$lead->id, $plan->id]))
            ->assertRedirect();

        $ws = $this->workspace->fresh();

        $this->assertFalse($plan->fresh()->is_active);
        $this->assertSame(3, (int) $ws->max_sessions, 'Batas yang sudah dibayar ikut dicabut.');
    }

    /** Enterprise tidak boleh muncul sebagai paket yang bisa dibeli sendiri. */
    public function test_enterprise_tidak_ikut_daftar_paket_yang_dijual(): void
    {
        $slug = array_map(fn ($p) => $p->slug, Plan::all());

        $this->assertNotContains('enterprise', $slug);
    }

    // ===================== Kalkulator perkiraan =====================

    /**
     * Kedua sisi harus sepakat.
     *
     * Kalkulator berjalan di peramban supaya angkanya berubah seketika, dan
     * server menghitungnya lagi saat permintaan masuk. Kalau keduanya
     * menyimpang, pengunjung melihat satu angka lalu tim menerima angka lain —
     * dan yang menemukan selisihnya adalah orang yang sudah telanjur menyebut
     * angka pertama di percakapan.
     */
    public function test_perkiraan_dihitung_dari_komponennya(): void
    {
        $k = PerkiraanEnterprise::komponen();

        // 3 nomor + 150.000 pesan = dasar + 2 nomor + 2 blok pesan.
        $hasil = PerkiraanEnterprise::hitung(nomor: 3, pesan: 150_000);

        $this->assertSame(
            $k['base'] + (2 * $k['per_session']) + (2 * $k['per_message_block']),
            $hasil['bulanan'],
        );
        $this->assertSame($hasil['bulanan'] * config('plans.yearly_multiplier'), $hasil['tahunan']);
        $this->assertSame(0, $hasil['sekali']);
    }

    /** Blok pesan dibulatkan ke ATAS: 60.000 butuh dua blok, bukan satu koma dua. */
    public function test_blok_pesan_dibulatkan_ke_atas(): void
    {
        $k = PerkiraanEnterprise::komponen();

        $pas = PerkiraanEnterprise::hitung(nomor: 1, pesan: 50_000);
        $lebihSedikit = PerkiraanEnterprise::hitung(nomor: 1, pesan: 50_001);

        $this->assertSame($k['base'], $pas['bulanan'], 'Blok pertama seharusnya sudah termasuk.');
        $this->assertSame($k['base'] + $k['per_message_block'], $lebihSedikit['bulanan']);
    }

    /**
     * Pendampingan dibayar SEKALI dan tidak boleh masuk total bulanan.
     *
     * Menjumlahkannya membuat angka bulanan terbaca lebih mahal dari yang
     * sebenarnya, dan itu menghilangkan pelanggan yang sebenarnya mampu.
     */
    public function test_pendampingan_tidak_masuk_total_bulanan(): void
    {
        $tanpa = PerkiraanEnterprise::hitung(nomor: 1, pesan: 0);
        $dengan = PerkiraanEnterprise::hitung(nomor: 1, pesan: 0, pendampingan: true);

        $this->assertSame($tanpa['bulanan'], $dengan['bulanan']);
        $this->assertGreaterThan(0, $dengan['sekali']);
    }

    /** Angka mustahil tidak boleh menghasilkan total yang terlihat masuk akal. */
    public function test_angka_di_luar_batas_dijepit(): void
    {
        $nol = PerkiraanEnterprise::hitung(nomor: 0, pesan: -5000);
        $satu = PerkiraanEnterprise::hitung(nomor: 1, pesan: 0);

        $this->assertSame($satu['bulanan'], $nol['bulanan']);
        $this->assertGreaterThan(0, $nol['bulanan']);
    }

    public function test_halaman_enterprise_terbuka_dan_memuat_kalkulatornya(): void
    {
        $this->get(route('enterprise'))
            ->assertOk()
            ->assertSee('Susun kebutuhan Anda')
            ->assertSee('Perkiraan biaya')
            // Kalimat ini WAJIB ada: angka yang tampak pasti lalu berubah saat
            // ditagihkan adalah janji yang dilanggar di hadapan orang yang baru
            // saja memutuskan membeli.
            ->assertSee('Ini perkiraan, bukan penawaran');
    }

    /**
     * Perkiraannya dihitung ULANG di server.
     *
     * Angka yang datang dari peramban bisa disunting siapa saja, dan penawaran
     * yang disusun di atas angka kiriman pengunjung adalah penawaran yang
     * harganya ditentukan pemohon.
     */
    public function test_perkiraan_tersimpan_dan_dihitung_ulang_di_server(): void
    {
        $this->isiForm([
            'estimated_sessions' => 3,
            'estimated_messages' => 150_000,
            'want_retention_months' => 24,
            'want_api_rate' => 600,
            'want_onboarding' => 1,
        ])->assertRedirect();

        $lead = EnterpriseLead::firstOrFail();

        $seharusnya = PerkiraanEnterprise::hitung(
            nomor: 3,
            pesan: 150_000,
            retensi24: true,
            api600: true,
            pendampingan: true,
        );

        $this->assertSame($seharusnya['bulanan'], (int) $lead->estimate_monthly);
        $this->assertSame($seharusnya['tahunan'], (int) $lead->estimate_yearly);
        $this->assertSame($seharusnya['sekali'], (int) $lead->estimate_once);
        $this->assertSame(24, (int) $lead->want_retention_months);
        $this->assertTrue((bool) $lead->want_onboarding);
    }

    public function test_pilihan_di_luar_daftar_ditolak(): void
    {
        $this->isiForm(['want_retention_months' => 99, 'want_api_rate' => 9999])
            ->assertSessionHasErrors(['want_retention_months', 'want_api_rate']);

        $this->assertSame(0, EnterpriseLead::count());
    }

    /**
     * Nomor publik Enterprise BUKAN nomor kabar tim, dan itu dijaga di sini.
     *
     * `admin_phone` adalah kotak masuk kami sendiri — bukti bayar, lead,
     * peringatan sistem. Kalau suatu saat ada yang menyederhanakannya jadi satu
     * env, nomor itu ikut ter-index di halaman publik dan kabar yang menuntut
     * tindakan tenggelam di antara chat orang asing. Nomornya juga harus sudah
     * ternormalisasi: `wa.me` menolak awalan 0.
     */
    public function test_tombol_whatsapp_memakai_nomor_publik_bukan_nomor_tim(): void
    {
        config([
            'billing.enterprise.whatsapp' => '082318280376',
            'billing.admin_phone' => '085774410978',
        ]);

        $this->get(route('enterprise'))
            ->assertOk()
            ->assertSee('Chat WhatsApp')
            ->assertSee('wa.me/6282318280376', false)
            ->assertDontSee('6285774410978', false);
    }

    /**
     * Tanpa nomor publik, tombolnya tidak dirender — bukan menuju wa.me kosong,
     * yang membuka WhatsApp ke layar galat. Formulirnya tetap ada, jadi halaman
     * ini tidak pernah kehilangan satu-satunya cara menghubungi.
     */
    public function test_tanpa_nomor_publik_tombol_whatsapp_tidak_muncul(): void
    {
        config(['billing.enterprise.whatsapp' => null]);

        $this->get(route('enterprise'))
            ->assertOk()
            ->assertDontSee('Chat WhatsApp')
            ->assertSee('name="email"', false);
    }

    public function test_halaman_depan_menampilkan_kartu_enterprise(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Enterprise')
            ->assertSee('Hitung perkiraan');
    }

    /**
     * Header halaman enterprise harus persis dengan header landing page,
     * memuat navigasi lengkap, dropdown, pencarian tema, dan drawer mobile.
     */
    public function test_halaman_enterprise_menampilkan_header_lengkap_seperti_halaman_depan(): void
    {
        $this->get(route('enterprise'))
            ->assertOk()
            ->assertSee('Pesanvo Gateway')
            ->assertSee('images/pesanvo.png')
            ->assertSee('Cara Kerja')
            ->assertSee('Fitur')
            ->assertSee('Harga')
            ->assertSee('Mitra')
            ->assertSee('Docs')
            ->assertSee('Produk')
            ->assertSee('Developer')
            ->assertSee('Sumber daya')
            ->assertSee('Masuk')
            ->assertSee('Buat akun')
            ->assertSee('aria-label="Menu Mobile"', false);
    }

    /**
     * Footer halaman enterprise harus persis dengan footer landing page,
     * memuat banner ajakan, pilar keamanan, 4 kolom link, copyright legal, dan kanal resmi Pesanvo.
     */
    public function test_halaman_enterprise_menampilkan_footer_lengkap_seperti_halaman_depan(): void
    {
        $this->get(route('enterprise'))
            ->assertOk()
            ->assertSee('Siap mengotomatiskan pesan WhatsApp skala besar?')
            ->assertSee('Enkripsi TLS 1.3 & API Token Scoped', false)
            ->assertSee('Isolasi Volume Sesi Terenkripsi')
            ->assertSee('Produk API')
            ->assertSee('Pusat Dokumentasi')
            ->assertSee('Pesanvo. All rights reserved.')
            ->assertSee('lynk.id/pesanvo')
            // Akun media sosial merek lama tidak boleh ikut tampil di footer.
            ->assertDontSee('instagram.com')
            ->assertDontSee('threads.net')
            ->assertDontSee('x.com/')
            ->assertDontSee('tiktok.com')
            ->assertDontSee('linkedin.com');
    }

    public function test_halaman_enterprise_menampilkan_kalkulator_slider_dan_tanpa_teks_lama(): void
    {
        $this->get(route('enterprise'))
            ->assertOk()
            ->assertDontSee('Susun sendiri perkiraannya di bawah')
            ->assertSee('id="retensi"', false)
            ->assertSee('type="range"', false)
            ->assertSee('x-model.number="retensi"', false)
            ->assertSee('id="api"', false)
            ->assertSee('x-model.number="api"', false);
    }
}
