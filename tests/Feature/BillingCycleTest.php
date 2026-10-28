<?php

namespace Tests\Feature;

use App\Jobs\BillingCycleJob;
use App\Models\Invoice;
use App\Models\Workspace;
use App\Services\Billing\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Siklus penagihan harian.
 *
 * Yang dijaga di sini bukan sekadar "statusnya berubah", tapi urutan dan jeda
 * antar tahapnya — karena itulah yang menentukan apakah pelanggan mengalami
 * layanan berhenti sebagai kebijakan yang sudah diberitahukan, atau sebagai
 * kerusakan yang tiba-tiba.
 */
class BillingCycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
    }

    private function workspace(string $slug = 'contoh'): Workspace
    {
        return Workspace::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'max_sessions' => 1,
            'monthly_message_quota' => 3000,
        ]);
    }

    private function langgananBerakhir(Workspace $workspace, string $kapan, string $status = 'active')
    {
        $subscription = app(SubscriptionService::class)->ensureFor($workspace);

        $subscription->forceFill([
            'status' => $status,
            'current_period_end' => now()->parse($kapan),
        ])->save();

        return $subscription;
    }

    /**
     * Menjalankan siklus lewat container, bukan dengan mendaftar dependensinya
     * satu per satu.
     *
     * Sebelumnya tiap pemanggilan menyebut dependensinya sendiri, dan itu
     * berarti setiap dependensi baru di `handle()` menjatuhkan sebelas tes
     * sekaligus dengan `ArgumentCountError` — kegagalan yang tidak ada
     * hubungannya dengan apa pun yang sedang diuji. `app()->call()`
     * menyelesaikan argumennya persis seperti antrean melakukannya di produksi.
     */
    private function jalankanSiklus(): void
    {
        app()->call([new BillingCycleJob, 'handle']);
    }

    public function test_tagihan_perpanjangan_terbit_sebelum_periode_berakhir(): void
    {
        $workspace = $this->workspace();
        $this->langgananBerakhir($workspace, '+2 days');

        $this->jalankanSiklus();

        $invoice = Invoice::first();

        $this->assertNotNull($invoice, 'Tagihan harus terbit sebelum jatuh tempo, bukan pada hari-H.');
        $this->assertSame('pending', $invoice->status);
        $this->assertSame($workspace->id, $invoice->workspace_id);
    }

    public function test_tidak_menerbitkan_tagihan_untuk_periode_yang_masih_jauh(): void
    {
        $this->langgananBerakhir($this->workspace(), '+20 days');

        $this->jalankanSiklus();

        $this->assertSame(0, Invoice::count());
    }

    public function test_dijalankan_dua_kali_tidak_menggandakan_tagihan(): void
    {
        $this->langgananBerakhir($this->workspace(), '+1 day');

        $this->jalankanSiklus();
        $this->jalankanSiklus();

        $this->assertSame(1, Invoice::count());
    }

    public function test_periode_habis_menghentikan_pengiriman_tanpa_memutus_nomor(): void
    {
        $workspace = $this->workspace();
        $subscription = $this->langgananBerakhir($workspace, '-1 day');

        $session = $workspace->sessions()->create([
            'name' => 'CS',
            'status' => 'connected',
            'phone_number' => '6281111111111',
            'connected_at' => now(),
        ]);

        $this->jalankanSiklus();

        $this->assertSame('past_due', $subscription->fresh()->status);

        // Workspace ditandai suspended supaya penegakan yang sudah ada di
        // MessageDispatcher dan AuthenticateApiKey ikut berlaku tanpa tambahan.
        $this->assertSame('suspended', $workspace->fresh()->status);

        // Tapi nomornya belum dilepas — itu baru terjadi setelah masa tenggang.
        $this->assertSame('connected', $session->fresh()->status);
    }

    public function test_sesi_baru_dilepas_setelah_masa_tenggang_habis(): void
    {
        $workspace = $this->workspace();
        $subscription = $this->langgananBerakhir($workspace, '-40 days');

        $subscription->forceFill([
            'status' => 'past_due',
            'past_due_at' => now()->subDays(config('billing.grace_days') + 1),
        ])->save();

        $session = $workspace->sessions()->create([
            'name' => 'CS',
            'status' => 'connected',
            'phone_number' => '6281111111111',
            'connected_at' => now(),
        ]);

        $this->jalankanSiklus();

        $this->assertSame('suspended', $subscription->fresh()->status);
        $this->assertSame('disconnected', $session->fresh()->status);

        // Nomornya tidak ikut dilepas. `disconnect()` cuma menghentikan proses;
        // `logout()` yang membuang kredensial — dan pelanggan yang kembali
        // membayar lalu diminta scan QR ulang adalah pelanggan yang tidak
        // jadi kembali.
        $this->assertSame('6281111111111', $session->fresh()->phone_number);
    }

    public function test_workspace_internal_tidak_pernah_ditagih(): void
    {
        $workspace = $this->workspace('pesanvo');
        $workspace->forceFill(['is_internal' => true])->save();

        $subscription = $this->langgananBerakhir($workspace, '-5 days');

        $this->jalankanSiklus();

        $this->assertSame(0, Invoice::count());
        $this->assertSame('active', $subscription->fresh()->status);
    }

    public function test_tagihan_lewat_batas_bayar_ditutup(): void
    {
        $workspace = $this->workspace();
        $invoice = app(SubscriptionService::class)->issueInvoice($workspace, 'prime', 'monthly');

        $invoice->forceFill(['due_at' => now()->subDay()])->save();

        $this->jalankanSiklus();

        // Kode uniknya harus bebas dipakai tagihan berikutnya, dan pelanggan
        // tidak boleh membayar nominal yang sudah tidak dicari siapa-siapa.
        $this->assertSame('expired', $invoice->fresh()->status);
    }

    /**
     * Tagihan yang sudah ada buktinya tidak boleh kedaluwarsa.
     *
     * Uangnya sudah dikirim; yang belum selesai adalah pemeriksaan di pihak
     * kami. Menandainya kedaluwarsa berarti pelanggan membuka halaman tagihan
     * dan membaca "batas waktu pembayaran sudah lewat" padahal sudah membayar.
     */
    public function test_tagihan_berbukti_tidak_ikut_kedaluwarsa(): void
    {
        $workspace = $this->workspace();

        $berbukti = app(SubscriptionService::class)->issueInvoice($workspace, 'prime', 'monthly');
        $berbukti->forceFill(['due_at' => now()->subDay(), 'proof_path' => 'bukti-bayar/contoh.jpg'])->save();

        $polos = app(SubscriptionService::class)->issueInvoice($workspace, 'elite', 'monthly');
        $polos->forceFill(['due_at' => now()->subDay()])->save();

        $this->jalankanSiklus();

        $this->assertSame('pending', $berbukti->fresh()->status, 'Bukti sudah masuk — tagihannya harus tetap terbuka.');
        $this->assertSame('expired', $polos->fresh()->status);
    }

    /**
     * Workspace yang belum pernah berlangganan tidak ikut ditagih otomatis dan
     * tidak ikut ditangguhkan — ia memang belum pernah mulai.
     */
    /**
     * Masa coba gratis tidak punya tanggal berakhir, jadi tidak ada yang bisa
     * ditagih perpanjangannya. Kalau siklus harian ikut menyentuhnya, pemakai
     * coba akan menerima tagihan untuk paket yang tidak pernah mereka pilih.
     */
    public function test_masa_coba_gratis_tidak_disentuh_siklus_harian(): void
    {
        $workspace = $this->workspace('belum-bayar');
        $subscription = app(SubscriptionService::class)->ensureFor($workspace);

        $this->jalankanSiklus();

        $this->assertSame(0, Invoice::count(), 'Yang belum pernah berlangganan tidak boleh ditagih otomatis.');
        $this->assertSame('trialing', $subscription->fresh()->status);
        $this->assertNull($subscription->fresh()->current_period_end);
        $this->assertSame('active', $workspace->fresh()->status);
    }

    public function test_membayar_setelah_ditangguhkan_memulihkan_layanan(): void
    {
        $workspace = $this->workspace();
        $subscription = $this->langgananBerakhir($workspace, '-40 days');

        $subscription->forceFill([
            'status' => 'past_due',
            'past_due_at' => now()->subDays(config('billing.grace_days') + 1),
        ])->save();

        $this->jalankanSiklus();
        $this->assertSame('suspended', $workspace->fresh()->status);

        $invoice = app(SubscriptionService::class)->issueInvoice($workspace, 'prime', 'monthly');
        app(SubscriptionService::class)->markPaid($invoice);

        $this->assertSame('active', $subscription->fresh()->status);
        $this->assertSame('active', $workspace->fresh()->status);
        $this->assertNull($subscription->fresh()->suspended_at);
    }
}
