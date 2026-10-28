<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\SubscriptionService;
use App\Support\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Langganan dari sisi pelanggan: memilih paket, menerima tagihan, membayar,
 * dan apa yang terjadi saat masa berlakunya habis.
 */
class BillingTest extends TestCase
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
            'monthly_message_quota' => 1000,
        ]);

        $this->workspace->members()->attach($this->owner->id, ['role' => 'owner']);
    }

    private function langganan()
    {
        return app(SubscriptionService::class)->ensureFor($this->workspace->fresh());
    }

    /**
     * Workspace baru lahir di paket coba gratis, bukan langsung berbayar dan
     * bukan pula terkunci total.
     *
     * Jatahnya lima pesan SEUMUR HIDUP workspace, tanpa tanggal berakhir. Yang
     * paling mudah salah di sini adalah memperlakukannya sebagai kuota bulanan
     * biasa: angkanya kembali penuh tiap tanggal 1, dan lima pesan gratis
     * berubah diam-diam menjadi lima pesan gratis setiap bulan selamanya.
     */
    public function test_workspace_baru_dapat_masa_coba_lima_pesan(): void
    {
        $this->actingAs($this->owner)->get(route('billing.index'))->assertOk();

        $workspace = $this->workspace->fresh();
        $subscription = $workspace->subscription;

        $this->assertSame(config('plans.free'), $subscription->plan_slug);
        $this->assertSame('trialing', $subscription->status);
        $this->assertTrue($subscription->isUsable());

        // Tanpa tanggal berakhir: yang menghabiskannya jumlah pesan, bukan waktu.
        $this->assertNull($subscription->current_period_end);
        $this->assertNull($workspace->service_until);

        // Dan workspace-nya HIDUP — pendaftar baru memang boleh menautkan nomor.
        $this->assertSame('active', $workspace->status);
        $this->assertTrue($workspace->isActive());
        $this->assertTrue($workspace->isFreeTier());
        $this->assertSame(5, $workspace->monthly_message_quota);
    }

    public function test_masa_coba_boleh_menautkan_nomor(): void
    {
        $this->actingAs($this->owner)->get(route('billing.index'));

        $this->actingAs($this->owner)
            ->post(route('sessions.store'), ['name' => 'CS'])
            ->assertRedirect(route('sessions.index'));

        $this->assertSame(1, $this->workspace->sessions()->count());
    }

    /**
     * Formulirnya ikut hilang saat langganan mati, bukan cuma penolakan di
     * belakang layar.
     *
     * Halaman Sesi dulu tetap menampilkan kotak "Buat sesi" lengkap dengan
     * tombolnya. Penolakannya sudah benar — `EnsureSubscriptionActive` melempar
     * POST-nya ke halaman langganan — tapi pengguna baru tahu setelah mengisi
     * nama dan menekan tombol, lalu mendarat di halaman lain tanpa isian yang
     * tadi diketiknya. Tombol yang hanya bisa gagal lebih buruk daripada tombol
     * yang tidak ada.
     */
    public function test_formulir_disembunyikan_saat_langganan_mati(): void
    {
        $this->langganan()->forceFill(['status' => 'past_due'])->save();

        $halaman = [
            route('sessions.index') => 'sessions.store',
            route('api-keys.index') => 'api-keys.store',
            route('templates.index') => 'templates.store',
            route('webhooks.index') => 'webhooks.store',
            route('messages.compose') => 'messages.send',
        ];

        foreach ($halaman as $url => $rutePengirim) {
            $isi = $this->actingAs($this->owner)->get($url)->assertOk()->getContent();

            $this->assertStringNotContainsString(
                'action="'.route($rutePengirim).'"',
                $isi,
                "Formulir {$rutePengirim} masih tampil padahal langganan sedang mati."
            );
        }
    }

    /**
     * Selama masa coba formulirnya justru HARUS ada — di situlah orang menguji
     * gateway-nya sebelum memutuskan membayar.
     */
    public function test_formulir_tetap_ada_selama_masa_coba(): void
    {
        $this->actingAs($this->owner)
            ->get(route('sessions.index'))
            ->assertOk()
            ->assertSee('action="'.route('sessions.store').'"', false);
    }

    public function test_membayar_membuka_workspace_yang_belum_pernah_berlangganan(): void
    {
        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        app(SubscriptionService::class)->markPaid($invoice);

        $workspace = $this->workspace->fresh();

        $this->assertSame('active', $workspace->subscription->status);
        $this->assertSame('active', $workspace->status);
        $this->assertTrue($workspace->subscription->current_period_end->isFuture());
    }

    /**
     * Empat alamat terpisah, bukan satu halaman panjang.
     *
     * Yang dijaga di sini bukan tata letaknya, tapi bahwa tiap langkah punya
     * URL sendiri yang bisa ditautkan langsung — dari spanduk, dari pengingat
     * WhatsApp, dari panel admin. Menggabungkannya lagi akan membuat tautan
     * itu mendarat di halaman yang benar tapi di bagian yang salah.
     */
    public function test_tiap_langkah_langganan_punya_alamat_sendiri(): void
    {
        foreach (['billing.index', 'billing.plans', 'billing.history'] as $rute) {
            $this->actingAs($this->owner)->get(route($rute))->assertOk();
        }
    }

    public function test_halaman_paket_menampilkan_seluruh_katalog(): void
    {
        $this->actingAs($this->owner)
            ->get(route('billing.plans'))
            ->assertOk()
            ->assertSee('Essentials')
            ->assertSee('Prime')
            ->assertSee('Elite')
            ->assertSee('149.000');
    }

    /**
     * Ringkasan hanya memuat tagihan yang masih butuh tindakan. Tagihan yang
     * sudah selesai tidak boleh berbagi tempat dengan yang sedang ditunggu.
     */
    public function test_ringkasan_hanya_menampilkan_tagihan_yang_masih_terbuka(): void
    {
        $lunas = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');
        app(SubscriptionService::class)->markPaid($lunas);

        $terbuka = app(SubscriptionService::class)->issueInvoice($this->workspace, 'elite', 'monthly');

        $ringkasan = $this->actingAs($this->owner)->get(route('billing.index'))->assertOk();

        // Dibaca dari isi halamannya saja: lonceng di bilah atas memang
        // menyebut nomor tagihan yang baru lunas, dan itu benar.
        $isi = $this->isiUtama($ringkasan);

        $this->assertStringContainsString($terbuka->number, $isi);
        $this->assertStringNotContainsString($lunas->number, $isi);

        // Riwayat memuat keduanya.
        $this->actingAs($this->owner)
            ->get(route('billing.history'))
            ->assertOk()
            ->assertSee($terbuka->number)
            ->assertSee($lunas->number);
    }

    public function test_memilih_paket_menerbitkan_tagihan_dengan_kode_unik(): void
    {
        $this->actingAs($this->owner)
            ->post(route('billing.checkout'), ['plan' => 'prime', 'period' => 'monthly'])
            ->assertRedirect();

        $invoice = Invoice::first();

        $this->assertSame('prime', $invoice->plan_slug);
        $this->assertSame(249_000, $invoice->amount);
        $this->assertGreaterThan(0, $invoice->unique_code);
        // Tagihan pertama workspace ini, jadi harga perkenalan ikut terpotong.
        // Yang dijaga di sini bukan angkanya melainkan bahwa totalnya benar-benar
        // turunan dari komponennya — kalau salah satu suku hilang dari rumus,
        // nominal yang ditransfer pelanggan tidak akan cocok dengan tagihannya.
        $this->assertSame(
            $invoice->amount + $invoice->tax_amount - $invoice->discount_amount + $invoice->unique_code,
            $invoice->total,
        );
        $this->assertSame(
            249_000 - Plan::get('prime')->introPrice('monthly'),
            (int) $invoice->intro_discount_amount,
        );
        $this->assertStringStartsWith('INV-WA-', $invoice->number);
    }

    public function test_harga_tahunan_sepuluh_kali_harga_bulanan(): void
    {
        $this->actingAs($this->owner)
            ->post(route('billing.checkout'), ['plan' => 'elite', 'period' => 'yearly']);

        $this->assertSame(4_490_000, Invoice::first()->amount);
    }

    /**
     * Mengklik "Bayar" dua kali tidak boleh menghasilkan dua nomor tagihan:
     * keduanya akan punya kode unik berbeda, pelanggan membayar salah satunya,
     * dan yang lain menggantung sampai kedaluwarsa.
     */
    public function test_klik_bayar_dua_kali_tidak_membuat_dua_tagihan(): void
    {
        $this->actingAs($this->owner)->post(route('billing.checkout'), ['plan' => 'prime', 'period' => 'monthly']);
        $this->actingAs($this->owner)->post(route('billing.checkout'), ['plan' => 'prime', 'period' => 'monthly']);

        $this->assertSame(1, Invoice::count());
    }

    public function test_pembayaran_menerapkan_batas_paket_ke_workspace(): void
    {
        $invoice = app(SubscriptionService::class)
            ->issueInvoice($this->workspace, 'elite', 'monthly');

        app(SubscriptionService::class)->markPaid($invoice);

        $workspace = $this->workspace->fresh();

        // Batas ditegakkan dari kolom workspace, jadi pembayaranlah yang harus
        // menyalinnya ke sana — kalau tidak, pelanggan membayar Elite dan tetap
        // dibatasi angka Essentials tanpa satu pun pesan galat yang menjelaskan.
        $elite = Plan::get('elite')->limits();

        $this->assertSame($elite['max_sessions'], $workspace->max_sessions);
        $this->assertSame($elite['monthly_message_quota'], $workspace->monthly_message_quota);
        $this->assertSame($elite['api_rate_limit_per_minute'], $workspace->api_rate_limit_per_minute);
        $this->assertSame('active', $workspace->subscription->status);
    }

    public function test_bayar_lebih_awal_tidak_menghanguskan_sisa_hari(): void
    {
        $subscription = $this->langganan();
        $subscription->forceFill(['current_period_end' => now()->addDays(5)])->save();

        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'essentials', 'monthly');
        app(SubscriptionService::class)->markPaid($invoice);

        // Periode baru menyambung dari akhir periode lama, bukan dari hari ini.
        $this->assertTrue(
            $subscription->fresh()->current_period_end->isSameDay(now()->addDays(5)->addMonth())
        );
    }

    public function test_bayar_terlambat_dihitung_dari_hari_ini(): void
    {
        $subscription = $this->langganan();
        $subscription->forceFill(['current_period_end' => now()->subMonths(2)])->save();

        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'essentials', 'monthly');
        app(SubscriptionService::class)->markPaid($invoice);

        // Menyambung dari tanggal lampau akan membuat pelanggan membayar penuh
        // untuk periode yang seluruhnya sudah berlalu, dan layanannya mati lagi
        // seketika setelah membayar.
        $this->assertTrue($subscription->fresh()->current_period_end->isSameDay(now()->addMonth()));
    }

    public function test_langganan_mati_menghentikan_perubahan_data_tapi_tidak_pembacaan(): void
    {
        $this->langganan()->forceFill(['status' => 'past_due'])->save();

        // Membaca tetap boleh: pelanggan yang berhenti berlangganan harus tetap
        // bisa melihat riwayatnya dan tahu kenapa layanannya berhenti.
        $this->actingAs($this->owner)->get(route('messages.index'))->assertOk();

        // Mengubah tidak.
        $this->actingAs($this->owner)
            ->post(route('sessions.store'), ['name' => 'CS'])
            ->assertRedirect(route('billing.index'));

        $this->assertSame(0, $this->workspace->sessions()->count());
    }

    public function test_halaman_tagihan_tetap_menerima_post_saat_langganan_mati(): void
    {
        $this->langganan()->forceFill(['status' => 'suspended'])->save();

        // Kalau halaman ini ikut terblokir, satu-satunya jalan keluar dari
        // keadaan tertangguh ikut tertutup.
        $this->actingAs($this->owner)
            ->post(route('billing.checkout'), ['plan' => 'essentials', 'period' => 'monthly'])
            ->assertRedirect();

        $this->assertSame(1, Invoice::count());
    }

    public function test_anggota_biasa_tidak_bisa_mengeluarkan_uang_workspace(): void
    {
        $member = User::create([
            'name' => 'Maya',
            'email' => 'maya@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->workspace->members()->attach($member->id, ['role' => 'member']);

        $this->actingAs($member)->get(route('billing.index'))->assertOk();

        $this->actingAs($member)
            ->post(route('billing.checkout'), ['plan' => 'elite', 'period' => 'yearly'])
            ->assertForbidden();
    }

    public function test_bukti_transfer_tersimpan_tapi_tidak_mengaktifkan_apa_pun(): void
    {
        Storage::fake('media');

        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        $this->actingAs($this->owner)
            ->post(route('billing.proof.upload', $invoice->id), [
                'bukti' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertRedirect();

        $invoice->refresh();

        $this->assertNotNull($invoice->proof_path);

        // Menganggap unggahan sebagai pembayaran berarti siapa pun bisa
        // menyalakan layanannya sendiri dengan gambar apa saja dari galeri.
        $this->assertSame('pending', $invoice->status);

        // Masih di paket coba gratis: yang memindahkannya cuma markPaid().
        $this->assertSame(config('plans.free'), $this->workspace->fresh()->subscription->plan_slug);
    }

    /**
     * Setelah bukti terkirim, pelanggan harus mendarat di halaman yang seluruh
     * isinya mengatakan "sudah kami terima".
     *
     * Kembali ke form dengan spanduk hijau tipis pernah membuat pelanggan
     * mengira unggahannya gagal, lalu membatalkan tagihannya 24 detik kemudian.
     */
    public function test_setelah_bukti_terkirim_diarahkan_ke_halaman_verifikasi(): void
    {
        Storage::fake('media');

        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        $this->actingAs($this->owner)
            ->post(route('billing.proof.upload', $invoice->id), [
                'bukti' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertRedirect(route('billing.verifying', $invoice->id));

        $this->actingAs($this->owner)
            ->get(route('billing.verifying', $invoice->id))
            ->assertOk()
            ->assertSee('Bukti Anda sudah kami terima');
    }

    public function test_halaman_verifikasi_menolak_tagihan_yang_belum_ada_buktinya(): void
    {
        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        $this->actingAs($this->owner)
            ->get(route('billing.verifying', $invoice->id))
            ->assertRedirect(route('billing.invoice', $invoice->id));
    }

    /**
     * Tagihan yang buktinya sudah dikirim tidak boleh dibatalkan pelanggan.
     *
     * Ini penjagaan yang paling menentukan di seluruh alur pembayaran: sekali
     * dibatalkan, pembayaran yang uangnya sudah masuk kehilangan tempatnya.
     */
    public function test_tagihan_yang_sudah_ada_buktinya_tidak_bisa_dibatalkan(): void
    {
        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');
        $invoice->forceFill(['proof_path' => 'bukti-bayar/contoh.jpg'])->save();

        $this->actingAs($this->owner)
            ->post(route('billing.invoice.cancel', $invoice->id))
            ->assertSessionHasErrors('tagihan');

        $this->assertSame('pending', $invoice->fresh()->status);
    }

    public function test_tagihan_tanpa_bukti_tetap_bisa_dibatalkan(): void
    {
        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        $this->actingAs($this->owner)
            ->post(route('billing.invoice.cancel', $invoice->id))
            ->assertRedirect();

        $this->assertSame('canceled', $invoice->fresh()->status);
    }

    public function test_konfirmasi_pembayaran_langsung_mengarahkan_ke_halaman_verifikasi(): void
    {
        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        $this->actingAs($this->owner)
            ->post(route('billing.invoice.confirm', $invoice->id), [
                'metode_bayar' => 'qris',
            ])
            ->assertRedirect(route('billing.verifying', $invoice->id));

        $fresh = $invoice->fresh();
        $this->assertNotNull($fresh->payment_confirmed_at);
        $this->assertSame('qris_manual', $fresh->channel);

        // Halaman verifikasi bisa dibuka dan memuat informasi verifikasi & nomor WhatsApp admin
        $this->actingAs($this->owner)
            ->get(route('billing.verifying', $invoice->id))
            ->assertOk()
            ->assertSee('Memverifikasi Pembayaran')
            ->assertSee('+62 823-1828-0376');

        // Tagihan yang sudah dikonfirmasi tidak bisa dibatalkan sendiri
        $this->actingAs($this->owner)
            ->post(route('billing.invoice.cancel', $invoice->id))
            ->assertSessionHasErrors('tagihan');
    }

    public function test_status_tagihan_bisa_ditanyakan_halaman_verifikasi(): void
    {
        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');
        $invoice->forceFill(['proof_path' => 'bukti-bayar/contoh.jpg'])->save();

        $this->actingAs($this->owner)
            ->getJson(route('billing.status', $invoice->id))
            ->assertOk()
            ->assertJson(['status' => 'pending', 'lunas' => false]);

        app(SubscriptionService::class)->markPaid($invoice->fresh());

        $this->actingAs($this->owner)
            ->getJson(route('billing.status', $invoice->id))
            ->assertOk()
            ->assertJson(['status' => 'paid', 'lunas' => true]);
    }

    public function test_status_tagihan_orang_lain_tidak_bisa_diintip(): void
    {
        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        $orangLain = User::create([
            'name' => 'Budi', 'email' => 'budi2@contoh.id', 'password' => Hash::make('rahasia12345'),
        ]);
        $lain = Workspace::create(['name' => 'Warung Lain', 'slug' => 'warung-lain-2', 'owner_id' => $orangLain->id]);
        $lain->members()->attach($orangLain->id, ['role' => 'owner']);

        $this->actingAs($orangLain)
            ->getJson(route('billing.status', $invoice->id))
            ->assertNotFound();
    }

    public function test_workspace_lain_tidak_bisa_membuka_tagihan_kita(): void
    {
        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        $orangLain = User::create([
            'name' => 'Budi',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $lain = Workspace::create([
            'name' => 'Warung Lain',
            'slug' => 'warung-lain',
            'owner_id' => $orangLain->id,
        ]);

        $lain->members()->attach($orangLain->id, ['role' => 'owner']);

        $this->actingAs($orangLain)
            ->get(route('billing.invoice', $invoice->id))
            ->assertNotFound();
    }

    public function test_halaman_bayar_menampilkan_qris_dengan_nominal_tagihan(): void
    {
        // Payload contoh yang sah; yang diuji bukan QRIS-nya (itu di QrisTest)
        // melainkan bahwa halaman benar-benar menyisipkan nominal tagihan ini.
        config(['billing.qris.payload' => $this->payloadQrisContoh()]);

        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        $this->actingAs($this->owner)
            ->get(route('billing.invoice', $invoice->id))
            ->assertOk()
            ->assertSee('54'.str_pad((string) strlen((string) $invoice->total), 2, '0', STR_PAD_LEFT).$invoice->total, false);
    }

    public function test_halaman_invoice_memuat_skrip_dan_elemen_render_qris_saat_payload_aktif(): void
    {
        config(['billing.qris.payload' => $this->payloadQrisContoh()]);

        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        $response = $this->actingAs($this->owner)
            ->get(route('billing.invoice', $invoice->id))
            ->assertOk()
            ->assertSee('data-qris', false)
            ->assertDontSee('QRIS Standar Nasional')
            ->assertSee('js/qrcode.min.js')
            ->assertSee('window.renderQris')
            ->assertSee('QRCode.toCanvas')
            ->assertSee('class="space-y-6">', false);

        // QRIS tidak boleh disembunyikan x-cloak saat metode awalnya QRIS
        $this->assertStringNotContainsString('x-show="metode === \'qris\'" x-cloak', $response->getContent());
        $this->assertStringContainsString('x-show="metode === \'qris\'"', $response->getContent());
    }

    public function test_halaman_invoice_menampilkan_rekening_bank_saat_dikonfigurasi(): void
    {
        config([
            'billing.qris.payload' => $this->payloadQrisContoh(),
            'billing.bank.name' => 'BCA',
            'billing.bank.account_number' => '8880123456',
            'billing.bank.account_holder' => 'PT NAMA PERUSAHAAN',
        ]);

        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        $this->actingAs($this->owner)
            ->get(route('billing.invoice', $invoice->id))
            ->assertOk()
            ->assertSee('Transfer bank')
            ->assertSee('BCA')
            ->assertSee('8880123456')
            ->assertSee('PT NAMA PERUSAHAAN');
    }

    public function test_alur_pembayaran_qris_unggah_bukti_sampai_verifikasi_admin(): void
    {
        Storage::fake('media');
        config(['billing.qris.payload' => $this->payloadQrisContoh()]);

        // 1. Tagihan terbit
        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');
        $this->assertSame('pending', $invoice->status);
        $this->assertNull($invoice->proof_path);

        // 2. Pelanggan buka halaman invoice (QRIS muncul)
        $this->actingAs($this->owner)
            ->get(route('billing.invoice', $invoice->id))
            ->assertOk()
            ->assertSee('data-qris', false);

        // 3. Pelanggan unggah bukti transfer
        $file = UploadedFile::fake()->image('bukti_transfer.png', 400, 400);
        $this->actingAs($this->owner)
            ->post(route('billing.proof.upload', $invoice->id), ['bukti' => $file])
            ->assertRedirect(route('billing.verifying', $invoice->id));

        $invoice->refresh();
        $this->assertNotNull($invoice->proof_path);
        Storage::disk('media')->assertExists($invoice->proof_path);

        // 4. Super admin memeriksa di panel admin
        $admin = User::create([
            'name' => 'Admin Pesanvo',
            'email' => 'admin@pesanvo.id',
            'password' => Hash::make('password123'),
            'is_super_admin' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.invoices'))
            ->assertOk()
            ->assertSee($invoice->number)
            ->assertSee('bukti masuk')
            ->assertSee(route('admin.invoices.paid', $invoice->id));

        // 5. Admin menandai lunas
        $this->actingAs($admin)
            ->post(route('admin.invoices.paid', $invoice->id), ['catatan' => 'Mutasi QRIS cocok'])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);
        $this->assertSame($admin->id, $invoice->paid_by_user_id);
        $this->assertTrue($this->workspace->fresh()->isActive());
    }

    public function test_payload_qris_rusak_tidak_ditawarkan_sebagai_metode(): void
    {
        // Kode QR rusak yang tetap tergambar adalah kegagalan yang baru terlihat
        // saat pelanggan sudah berdiri di depan layar dengan aplikasi banknya.
        // Yang dijaga di sini lebih jauh dari itu: metodenya tidak boleh muncul
        // sebagai pilihan sama sekali, karena memilihnya berujung jalan buntu.
        config([
            'billing.qris.payload' => '00020101021126320014ID.CO.QRIS.WWW63040000',
            'billing.bank.account_number' => null,
        ]);

        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        $this->actingAs($this->owner)
            ->get(route('billing.invoice', $invoice->id))
            ->assertOk()
            ->assertSee('Belum ada metode pembayaran yang aktif')
            ->assertDontSee('data-qris', false);
    }

    public function test_transfer_bank_ditawarkan_saat_rekening_terisi(): void
    {
        config([
            'billing.qris.payload' => null,
            'billing.bank.name' => 'BCA',
            'billing.bank.account_number' => '1234567890',
            'billing.bank.account_holder' => 'PT Pesanvo',
        ]);

        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        $this->actingAs($this->owner)
            ->get(route('billing.invoice', $invoice->id))
            ->assertOk()
            ->assertSee('Transfer bank')
            ->assertSee('1234567890');
    }

    public function test_data_penagihan_tersimpan_tanpa_menyentuh_tagihan(): void
    {
        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        $this->actingAs($this->owner)
            ->post(route('billing.details', $invoice->id), [
                'billing_type' => 'individu',
                'billing_name' => 'PT Sinar Jaya',
                'billing_email' => 'keuangan@sinarjaya.id',
                'billing_phone' => '081234567890',
                'billing_bank_name' => 'Bank Mandiri',
                'billing_bank_account' => '1234567890',
                'billing_bank_holder' => 'PT Sinar Jaya',
                'billing_province' => 'DKI Jakarta',
                'billing_city' => 'Jakarta Selatan',
                'billing_district' => 'Kebayoran Baru',
                'billing_address' => 'Jl. Gatot Subroto No. 12',
            ])
            ->assertRedirect();

        $workspace = $this->workspace->fresh();

        $this->assertSame('PT Sinar Jaya', $workspace->billing_name);
        $this->assertSame('keuangan@sinarjaya.id', $workspace->billing_email);

        // Nomor dinormalkan; pengingat ke `08...` gagal diam-diam.
        $this->assertSame('6281234567890', $workspace->billing_phone);

        // Tagihannya sendiri tidak berubah sama sekali.
        $this->assertSame('pending', $invoice->fresh()->status);
    }

    public function test_anggota_biasa_tidak_bisa_mengubah_data_penagihan(): void
    {
        $member = User::create([
            'name' => 'Maya',
            'email' => 'maya2@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->workspace->members()->attach($member->id, ['role' => 'member']);

        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        $this->actingAs($member)
            ->post(route('billing.details', $invoice->id), [
                'billing_name' => 'Diubah orang lain',
                'billing_email' => 'maya2@contoh.id',
            ])
            ->assertForbidden();

        $this->assertNull($this->workspace->fresh()->billing_name);
    }

    public function test_tagihan_lewat_tempo_tidak_lagi_menampilkan_kode_qr(): void
    {
        config(['billing.qris.payload' => $this->payloadQrisContoh()]);

        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');
        $invoice->forceFill(['due_at' => now()->subDay()])->save();

        $this->actingAs($this->owner)
            ->get(route('billing.invoice', $invoice->id))
            ->assertOk()
            ->assertSee('Batas waktu pembayaran sudah lewat')
            ->assertDontSee('data-qris', false);
    }

    /** Payload QRIS statis contoh, sah menurut CRC. Sama dengan yang dipakai QrisTest. */
    private function payloadQrisContoh(): string
    {
        $isi = '000201'
            .'010211'
            .'26370014ID.CO.QRIS.WWW0215ID1234567890123'
            .'52045812'
            .'5303360'
            .'5802ID'
            .'5908PESANVO'
            .'6007JAKARTA'
            .'6304';

        $crc = 0xFFFF;

        for ($i = 0, $n = strlen($isi); $i < $n; $i++) {
            $crc ^= ord($isi[$i]) << 8;

            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return $isi.strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    public function test_paket_yang_tidak_ada_ditolak(): void
    {
        $this->actingAs($this->owner)
            ->post(route('billing.checkout'), ['plan' => 'platinum', 'period' => 'monthly'])
            ->assertSessionHasErrors('plan');

        $this->assertSame(0, Invoice::count());
    }

    public function test_dashboard_menampilkan_alert_tagihan_pending_dengan_tombol_wa(): void
    {
        // Nomornya dibaca dari config, bukan ditulis mati di Blade — tes ini
        // memastikan nomor bisnis yang berganti benar-benar ikut tampil.
        config(['billing.enterprise.whatsapp' => '085808749131']);
        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        // Sebelum dikonfirmasi bayar: muncul alert transaksi pending
        $this->actingAs($this->owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Transaksi Pending')
            ->assertSee($invoice->number)
            ->assertSee('Hubungi Admin WA')
            ->assertSee('wa.me/6285808749131', false);

        // Setelah dikonfirmasi bayar: muncul alert sedang diverifikasi
        $invoice->forceFill(['payment_confirmed_at' => now()])->save();

        $this->actingAs($this->owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Verifikasi Pending')
            ->assertSee('Transaksi Anda masih pending dalam proses verifikasi')
            ->assertSee('Cek Status');
    }
}
