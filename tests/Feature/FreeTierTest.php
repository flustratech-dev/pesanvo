<?php

namespace Tests\Feature;

use App\Jobs\SendMessageJob;
use App\Models\ApiKey;
use App\Models\Message;
use App\Models\User;
use App\Models\WaSession;
use App\Models\Workspace;
use App\Services\Billing\SubscriptionService;
use App\Services\MessageDispatcher;
use App\Services\Providers\ProviderManager;
use App\Services\WebhookDispatcher;
use App\Support\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

/**
 * Jatah coba gratis, dan apa yang menahannya supaya tidak bocor.
 *
 * Ada tiga cara jatah ini bisa berubah menjadi layanan gratis selamanya, dan
 * ketiganya pernah nyaris terjadi di kode ini:
 *
 *  1. Dihitung sebagai kuota BULANAN — angkanya kembali penuh tiap tanggal 1.
 *  2. Dihitung dari tabel `messages` — baris pesan dipangkas mengikuti retensi
 *     paket, dan jatah yang sudah terpakai ikut hilang bersamanya.
 *  3. Diperiksa hanya saat mengantre — pesan yang sudah telanjur di antrean
 *     tetap terkirim setelah jatahnya habis.
 *
 * Tiap nomor di atas punya tesnya sendiri di bawah ini.
 */
class FreeTierTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    private WaSession $session;

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
        ]);

        $this->workspace->members()->attach($this->owner->id, ['role' => 'owner']);

        // Lewat service-nya, bukan disetel tangan: yang sedang diuji justru
        // keadaan yang dibuat `ensureFor()` untuk setiap pendaftar baru.
        app(SubscriptionService::class)->ensureFor($this->workspace);
        $this->workspace->refresh();

        $this->session = $this->workspace->sessions()->create([
            'name' => 'CS',
            'status' => 'connected',
            'phone_number' => '6281111111111',
            'connected_at' => now(),
        ]);
    }

    /**
     * Sesi dimuat ulang tiap kali, seperti di produksi: tiap permintaan HTTP
     * membaca barisnya dari nol. Memakai objek yang sama sepanjang tes membuat
     * relasi `workspace`-nya tersimpan di memori, dan perubahan paket yang baru
     * saja terjadi tidak akan terlihat — tes lulus atau gagal karena cache
     * Eloquent, bukan karena aturannya.
     */
    private function kirim(int $berapa): void
    {
        $dispatcher = app(MessageDispatcher::class);

        for ($i = 1; $i <= $berapa; $i++) {
            $dispatcher->queue(
                WaSession::with('workspace')->findOrFail($this->session->id),
                '6282222222222',
                ['body' => "halo {$i}"]
            );
        }
    }

    public function test_lima_pesan_pertama_boleh_terkirim(): void
    {
        $this->kirim(5);

        $this->assertSame(5, Message::where('workspace_id', $this->workspace->id)->count());
        $this->assertSame(5, $this->workspace->fresh()->freeMessagesUsed());
    }

    public function test_pesan_keenam_ditolak(): void
    {
        $this->kirim(5);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Jatah 5 pesan coba gratis sudah habis');

        $this->kirim(1);
    }

    /**
     * Akun bebas yang workspace-nya masih berpaket coba gratis tidak boleh
     * berhenti di pesan kelima. Dulu cabang jatah coba gratis diperiksa lebih
     * dulu daripada pembebasan, jadi tanda "bebas tagihan" tidak berpengaruh
     * apa pun terhadap batas lima pesan.
     */
    public function test_akun_bebas_tidak_dibatasi_jatah_coba_gratis(): void
    {
        $this->owner->forceFill(['is_exempt' => true])->save();

        $this->kirim(8);

        $this->assertSame(8, Message::where('workspace_id', $this->workspace->id)->count());
    }

    /**
     * Nomor 1 di daftar atas: jatah tidak boleh pulih saat bulan berganti.
     */
    public function test_jatah_tidak_pulih_di_bulan_berikutnya(): void
    {
        $this->kirim(5);

        // Bulan berganti — dan dengan itu baris `usage_counters` untuk periode
        // baru pun kosong. Yang dibaca harus tetap jumlah seumur hidupnya.
        $this->travel(35)->days();

        $this->assertSame(0, $this->workspace->fresh()->currentUsage()->messages_sent);
        $this->assertSame(5, $this->workspace->fresh()->freeMessagesUsed());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Jatah 5 pesan coba gratis sudah habis');

        $this->kirim(1);
    }

    /**
     * Nomor 2: riwayat pesan boleh dipangkas, jatah terpakai tidak ikut hilang.
     */
    public function test_jatah_tidak_pulih_saat_riwayat_pesan_dipangkas(): void
    {
        $this->kirim(5);

        Message::where('workspace_id', $this->workspace->id)->forceDelete();

        $this->assertSame(5, $this->workspace->fresh()->freeMessagesUsed());

        $this->expectException(RuntimeException::class);

        $this->kirim(1);
    }

    /**
     * Membayar mengubah paketnya, dan dengan itu jatah seumur hidup berhenti
     * berlaku — diganti kuota bulanan paket yang dibeli.
     */
    public function test_membayar_melepaskan_batas_lima_pesan(): void
    {
        $this->kirim(5);

        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');
        app(SubscriptionService::class)->markPaid($invoice);

        $workspace = $this->workspace->fresh();

        $this->assertFalse($workspace->isFreeTier());
        $this->assertSame(
            Plan::get('prime')->limits()['monthly_message_quota'],
            $workspace->monthly_message_quota
        );

        // Dan pesan keenam yang tadi ditolak sekarang lewat.
        $this->kirim(1);

        $this->assertSame(6, Message::where('workspace_id', $this->workspace->id)->count());
    }

    /**
     * Nomor 3, dan celah yang sama persis berlaku untuk langganan berbayar yang
     * habis: antara antre dan kirim bisa lewat berjam-jam.
     */
    public function test_pesan_di_antrean_tidak_terkirim_setelah_layanan_mati(): void
    {
        Queue::fake();

        $this->kirim(1);
        $message = Message::where('workspace_id', $this->workspace->id)->firstOrFail();

        // Layanannya mati sesudah pesan masuk antrean.
        $this->workspace->forceFill(['status' => 'suspended'])->save();

        (new SendMessageJob($message->id))->handle(
            app(ProviderManager::class),
            app(MessageDispatcher::class),
            app(WebhookDispatcher::class),
        );

        $message->refresh();

        $this->assertSame('failed', $message->status);
        $this->assertStringContainsString('tidak aktif', $message->error);
    }

    /**
     * Masa berlaku yang lewat harus menolak SEKETIKA, bukan menunggu
     * `BillingCycleJob` pukul 08:00 — di antara keduanya ada delapan jam
     * ketika langganan sudah habis tapi kolom statusnya belum sempat berubah.
     */
    public function test_masa_berlaku_lewat_langsung_menolak_tanpa_menunggu_job_harian(): void
    {
        $this->berlangganan($this->workspace, 'prime');

        // Persis keadaan pukul 00:01: tanggalnya lewat, statusnya masih active
        // karena job hariannya baru berjalan tujuh jam lagi.
        $this->workspace->forceFill([
            'status' => 'active',
            'service_until' => now()->subMinute(),
        ])->save();

        $workspace = $this->workspace->fresh();

        $this->assertSame('active', $workspace->status);
        $this->assertTrue($workspace->serviceExpired());
        $this->assertFalse($workspace->isActive());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Masa berlaku langganan workspace ini sudah habis');

        app(MessageDispatcher::class)->queue($this->session, '6282222222222', ['body' => 'halo']);
    }

    /**
     * Dan lewat REST API — jalur yang dipakai aplikasi lain, tempat kegagalan
     * paling sulit terlihat karena tidak ada manusia yang menatap layarnya.
     */
    public function test_api_menolak_setelah_masa_berlaku_lewat(): void
    {
        $this->berlangganan($this->workspace, 'prime');

        [$key, $plain] = array_values(ApiKey::issue($this->workspace, 'integrasi', ['*']));

        $this->workspace->forceFill([
            'status' => 'active',
            'service_until' => now()->subMinute(),
        ])->save();

        $this->withHeader('X-Api-Key', $plain)
            ->postJson('/api/v1/messages/text', [
                'to' => '6282222222222',
                'message' => 'halo',
            ])
            ->assertStatus(403);
    }

    /**
     * Jatah coba sekali per pemilik, bukan sekali per workspace.
     *
     * Tanpa ini satu akun tinggal menekan "Workspace Baru" berulang kali untuk
     * mendapat lima pesan gratis sebanyak yang ia mau — dan tiap workspace coba
     * menahan satu sesi WhatsApp dari tiga yang tersedia untuk seluruh
     * pelanggan. Kapasitasnya habis oleh yang belum membayar sepeser pun.
     */
    public function test_workspace_kedua_tidak_dapat_masa_coba_lagi(): void
    {
        $kedua = Workspace::create([
            'name' => 'Cabang Dua',
            'slug' => 'cabang-dua',
            'owner_id' => $this->owner->id,
            'owner_email' => $this->owner->email,
        ]);
        $kedua->members()->attach($this->owner->id, ['role' => 'owner']);

        $subscription = app(SubscriptionService::class)->ensureFor($kedua);
        $kedua->refresh();

        $this->assertSame('unpaid', $subscription->status);
        $this->assertFalse($kedua->isFreeTier());
        $this->assertSame('suspended', $kedua->status);
        $this->assertFalse($kedua->isActive());
    }

    /**
     * Tapi pemilik yang BERBEDA tetap dapat jatahnya sendiri — batas ini
     * menutup penyalahgunaan, bukan menutup pendaftar baru.
     */
    public function test_pemilik_lain_tetap_dapat_masa_coba(): void
    {
        $orangLain = User::create([
            'name' => 'Budi',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $milikBudi = Workspace::create([
            'name' => 'Toko Budi',
            'slug' => 'toko-budi',
            'owner_id' => $orangLain->id,
            'owner_email' => $orangLain->email,
        ]);
        $milikBudi->members()->attach($orangLain->id, ['role' => 'owner']);

        $subscription = app(SubscriptionService::class)->ensureFor($milikBudi);

        $this->assertSame('trialing', $subscription->status);
        $this->assertSame(config('plans.free'), $subscription->plan_slug);
        $this->assertTrue($milikBudi->fresh()->isActive());
    }
}
