<?php

namespace Tests\Feature;

use App\Jobs\KabariDaftarTungguJob;
use App\Models\Notification;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Models\Workspace;
use App\Services\Billing\SubscriptionService;
use App\Services\Notifications\EmailNotifier;
use App\Services\Notifications\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Kapasitas penuh: ditolak, TAPI dicatat.
 *
 * Menolak saja membuang dua hal sekaligus. Yang pertama pelanggannya — orang
 * yang ditolak tanpa jalan lain mencari gateway lain hari itu juga. Yang kedua
 * lebih berharga dan tidak ada di tempat lain mana pun: berapa banyak
 * permintaan yang tidak bisa kami layani. Tanpa catatan itu, kapasitas penuh
 * terlihat sebagai grafik pendaftaran yang datar, dan grafik datar terbaca
 * "tidak ada peminat" alih-alih "peminatnya ditolak di pintu".
 */
class DaftarTungguTest extends TestCase
{
    use RefreshDatabase;

    private User $pengguna;

    protected function setUp(): void
    {
        parent::setUp();

        config(['gateway.engine.max_sessions' => 3]);

        $this->pengguna = User::create([
            'name' => 'Owner',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        // Kabar untuk tim disalurkan ke pengguna ber-is_super_admin
        // (Notifier::keAdmin). Tanpa satu pun admin, seluruh kabar itu diam —
        // keadaan yang tidak mungkin di produksi (panel /admin butuh admin)
        // tapi harus disiapkan di sini supaya yang diuji jalurnya, bukan
        // ketiadaan penerimanya.
        User::create([
            'name' => 'Admin',
            'email' => 'admin@pesanvo.id',
            'password' => Hash::make('rahasia12345'),
            'is_super_admin' => true,
        ]);
    }

    private function workspace(string $slug, int $slot, ?User $pemilik = null): Workspace
    {
        $pemilik ??= User::create([
            'name' => $slug,
            'email' => $slug.'@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $ws = Workspace::create([
            'name' => $slug,
            'slug' => $slug,
            'owner_id' => $pemilik->id,
            'owner_email' => $pemilik->email,
            'billing_email' => $pemilik->email,
            'max_sessions' => $slot,
            'status' => 'active',
        ]);

        $ws->members()->attach($pemilik->id, ['role' => 'owner']);

        return $ws;
    }

    private function berbayar(string $slug, int $slot): Workspace
    {
        $ws = $this->workspace($slug, $slot);

        Subscription::create([
            'workspace_id' => $ws->id,
            'plan_slug' => 'essentials',
            'status' => 'active',
            'period' => 'monthly',
            'current_period_end' => now()->addMonth(),
        ]);

        return $ws;
    }

    private function penuhkan(): void
    {
        $this->berbayar('a', 1);
        $this->berbayar('b', 1);
        $this->berbayar('c', 1);
    }

    private function cobaCheckout(Workspace $ws, string $plan = 'essentials')
    {
        return $this->actingAs($this->pengguna)
            ->withSession(['current_workspace_id' => $ws->id])
            ->post(route('billing.checkout'), ['plan' => $plan, 'period' => 'monthly']);
    }

    public function test_yang_ditolak_masuk_daftar_tunggu(): void
    {
        $this->penuhkan();
        $baru = $this->workspace('d', 1, $this->pengguna);

        $this->cobaCheckout($baru)->assertSessionHasErrors('plan');

        $antrean = WaitlistEntry::where('workspace_id', $baru->id)->first();

        $this->assertNotNull($antrean, 'Permintaan yang ditolak hilang tanpa jejak.');
        $this->assertSame('essentials', $antrean->plan_slug);
        $this->assertSame(1, $antrean->slots);
        $this->assertNull($antrean->notified_at);
        $this->assertSame(0, $baru->invoices()->count());
    }

    public function test_kalimat_penolakan_menyebut_daftar_tunggu(): void
    {
        $this->penuhkan();
        $baru = $this->workspace('d', 1, $this->pengguna);

        $galat = $this->cobaCheckout($baru)->getSession()->get('errors')->first('plan');

        $this->assertStringContainsString('daftar tunggu', $galat);
    }

    /**
     * Menekan tombolnya lima kali adalah satu permintaan, bukan lima — dan
     * urutan antreannya tidak boleh berubah karena mencoba lagi.
     */
    public function test_mencoba_berulang_tidak_menggandakan_antrean(): void
    {
        $this->penuhkan();
        $baru = $this->workspace('d', 1, $this->pengguna);

        $this->cobaCheckout($baru);

        $urutanAwal = WaitlistEntry::where('workspace_id', $baru->id)->value('created_at');

        for ($i = 0; $i < 4; $i++) {
            $this->cobaCheckout($baru);
        }

        $this->assertSame(1, WaitlistEntry::where('workspace_id', $baru->id)->count());
        $this->assertEquals($urutanAwal, WaitlistEntry::where('workspace_id', $baru->id)->value('created_at'));
    }

    public function test_yang_sudah_menunggu_diberi_kalimat_berbeda(): void
    {
        $this->penuhkan();
        $baru = $this->workspace('d', 1, $this->pengguna);

        $this->cobaCheckout($baru);

        $galat = $this->cobaCheckout($baru)->getSession()->get('errors')->first('plan');

        $this->assertStringContainsString('sudah tercatat', $galat);
    }

    /**
     * Kalimat penolakan menjanjikan tim sudah diberi tahu. Kalau tidak ada
     * kabar yang benar-benar terkirim, janji itu sopan dan bohong.
     */
    public function test_tim_benar_benar_dikabari(): void
    {
        $this->penuhkan();
        $baru = $this->workspace('d', 1, $this->pengguna);

        $this->cobaCheckout($baru);

        $this->assertGreaterThan(
            0,
            Notification::where('type', 'kapasitas.penuh')->where('audience', 'admin')->count(),
            'Tidak satu pun kabar sampai ke tim; kalimat penolakannya jadi bohong.'
        );
    }

    // --- Saat kapasitas bebas lagi ------------------------------------------

    public function test_daftar_tunggu_dikabari_saat_slot_bebas(): void
    {
        $penuh = $this->berbayar('a', 1);
        $this->berbayar('b', 1);
        $this->berbayar('c', 1);

        $baru = $this->workspace('d', 1, $this->pengguna);
        $this->cobaCheckout($baru);

        // Satu pelanggan berhenti: slotnya bebas.
        $penuh->forceFill(['status' => 'suspended'])->save();

        (new KabariDaftarTungguJob)->handle(
            app(Notifier::class),
            app(EmailNotifier::class),
        );

        $this->assertNotNull(
            WaitlistEntry::where('workspace_id', $baru->id)->value('notified_at'),
            'Slot bebas tapi yang menunggu tidak dikabari.'
        );

        $this->assertSame(
            1,
            Notification::where('type', 'kapasitas.tersedia')->count(),
        );
    }

    public function test_tidak_dikabari_selama_kapasitas_masih_penuh(): void
    {
        $this->penuhkan();
        $baru = $this->workspace('d', 1, $this->pengguna);
        $this->cobaCheckout($baru);

        (new KabariDaftarTungguJob)->handle(
            app(Notifier::class),
            app(EmailNotifier::class),
        );

        $this->assertNull(WaitlistEntry::where('workspace_id', $baru->id)->value('notified_at'));
        $this->assertSame(0, Notification::where('type', 'kapasitas.tersedia')->count());
    }

    /**
     * Elite butuh dua slot. Mengabarinya saat baru satu slot bebas berarti
     * menjanjikan sesuatu yang tetap akan gagal di checkout — dan gagal untuk
     * kedua kalinya, setelah dijanjikan.
     */
    public function test_yang_butuh_dua_slot_tidak_dikabari_saat_baru_satu_bebas(): void
    {
        $penuh = $this->berbayar('a', 1);
        $this->berbayar('b', 1);
        $this->berbayar('c', 1);

        $baru = $this->workspace('d', 1, $this->pengguna);
        $this->cobaCheckout($baru, 'elite');

        $this->assertSame(2, WaitlistEntry::where('workspace_id', $baru->id)->value('slots'));

        $penuh->forceFill(['status' => 'suspended'])->save();

        (new KabariDaftarTungguJob)->handle(
            app(Notifier::class),
            app(EmailNotifier::class),
        );

        $this->assertNull(
            WaitlistEntry::where('workspace_id', $baru->id)->value('notified_at'),
            'Peminat Elite dikabari padahal baru satu dari dua slot yang dibutuhkannya bebas.'
        );
    }

    public function test_dikabari_sekali_saja_walau_job_berjalan_berkali_kali(): void
    {
        $penuh = $this->berbayar('a', 1);
        $this->berbayar('b', 1);
        $this->berbayar('c', 1);

        $baru = $this->workspace('d', 1, $this->pengguna);
        $this->cobaCheckout($baru);

        $penuh->forceFill(['status' => 'suspended'])->save();

        for ($i = 0; $i < 3; $i++) {
            (new KabariDaftarTungguJob)->handle(
                app(Notifier::class),
                app(EmailNotifier::class),
            );
        }

        $this->assertSame(1, Notification::where('type', 'kapasitas.tersedia')->count());
    }

    /**
     * Barisnya tidak dihapus setelah pelanggan akhirnya membayar: selisih
     * `converted_at` dengan `created_at` adalah lama tunggu sebenarnya, dan
     * itulah yang menjawab kapan kapasitas perlu ditambah.
     */
    public function test_konversi_tercatat_bukan_dihapus(): void
    {
        $penuh = $this->berbayar('a', 1);
        $this->berbayar('b', 1);
        $this->berbayar('c', 1);

        $baru = $this->workspace('d', 1, $this->pengguna);
        $this->cobaCheckout($baru);

        $penuh->forceFill(['status' => 'suspended'])->save();

        $langganan = app(SubscriptionService::class);
        $tagihan = $langganan->issueInvoice($baru, 'essentials', 'monthly');
        $langganan->markPaid($tagihan);

        $antrean = WaitlistEntry::where('workspace_id', $baru->id)->first();

        $this->assertNotNull($antrean, 'Barisnya dihapus; bukti kapasitas pernah menghambat penjualan ikut hilang.');
        $this->assertNotNull($antrean->converted_at);
    }
}
