<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\SpecialNumber;
use App\Models\User;
use App\Models\WaSession;
use App\Models\Workspace;
use App\Services\Billing\SubscriptionService;
use App\Services\MessageDispatcher;
use App\Services\Notifications\WhatsAppNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Pengecualian: nomor perusahaan sendiri, akun yang tidak ditagih, dan nomor
 * pengirim pemberitahuan.
 *
 * Yang paling penting dijaga di sini bukan bahwa pengecualiannya bekerja,
 * melainkan bahwa ia **berhenti di batasnya**. Pengecualian yang bocor lebih
 * jauh dari maksudnya adalah cara paling halus sebuah SaaS berhenti menagih
 * tanpa ada yang menyadarinya: nomor kami menumpang di workspace pelanggan,
 * lalu seluruh workspace itu ikut gratis selamanya.
 */
class PengecualianTest extends TestCase
{
    use RefreshDatabase;

    private User $pemilik;

    private Workspace $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->pemilik = User::create([
            'name' => 'Pelanggan',
            'email' => 'pelanggan@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->pelanggan = Workspace::create([
            'name' => 'Toko Pelanggan',
            'slug' => 'toko-pelanggan',
            'owner_id' => $this->pemilik->id,
            'owner_email' => $this->pemilik->email,
            'max_sessions' => 1,
            'monthly_message_quota' => 100,
        ]);

        $this->pelanggan->members()->attach($this->pemilik->id, ['role' => 'owner']);
    }

    private function sesi(Workspace $w, string $nomor, string $nama = 'CS'): WaSession
    {
        return $w->sessions()->create([
            'name' => $nama,
            'status' => 'connected',
            'driver' => 'wwebjs',
            'auto_reconnect' => true,
            'phone_number' => $nomor,
            'connected_at' => now(),
        ]);
    }

    private function daftarkanIstimewa(string $nomor = '6282318280376'): SpecialNumber
    {
        return SpecialNumber::create(['phone' => $nomor, 'label' => 'Nomor perusahaan']);
    }

    // ================= Nomor istimewa =================

    public function test_nomor_istimewa_tidak_menghitung_jatah_sesi(): void
    {
        $this->daftarkanIstimewa();
        $this->berlangganan($this->pelanggan, 'essentials');
        $this->pelanggan->forceFill(['max_sessions' => 1])->save();

        $this->sesi($this->pelanggan, '6282318280376', 'Nomor perusahaan');

        // Jatahnya 1 dan sudah ada satu sesi — tapi sesi itu nomor kami, jadi
        // pelanggan masih boleh menautkan nomornya sendiri.
        $this->assertTrue($this->pelanggan->fresh()->canAddSession());
        $this->assertSame(0, $this->pelanggan->fresh()->sessionsTerhitung());

        $this->sesi($this->pelanggan, '6289999999999', 'CS pelanggan');

        // Sekarang jatahnya benar-benar terpakai.
        $this->assertSame(1, $this->pelanggan->fresh()->sessionsTerhitung());
        $this->assertFalse($this->pelanggan->fresh()->canAddSession());
    }

    public function test_nomor_istimewa_tetap_bisa_mengirim_walau_workspace_mati(): void
    {
        $this->daftarkanIstimewa();
        $sesi = $this->sesi($this->pelanggan, '6282318280376');

        $this->pelanggan->forceFill(['status' => 'suspended'])->save();

        app(MessageDispatcher::class)->queue(
            WaSession::with('workspace')->findOrFail($sesi->id),
            '6289999999999',
            ['body' => 'halo']
        );

        $this->assertSame(1, $this->pelanggan->messages()->count());
    }

    /**
     * Batasnya: menumpang TIDAK membebaskan workspace yang ditumpangi.
     */
    public function test_menumpangnya_nomor_istimewa_tidak_membebaskan_workspace(): void
    {
        $this->daftarkanIstimewa();
        $this->sesi($this->pelanggan, '6282318280376');

        $sesiPelanggan = $this->sesi($this->pelanggan, '6289999999999', 'CS pelanggan');
        $this->pelanggan->forceFill(['status' => 'suspended'])->save();

        $this->expectException(RuntimeException::class);

        app(MessageDispatcher::class)->queue(
            WaSession::with('workspace')->findOrFail($sesiPelanggan->id),
            '6288888888888',
            ['body' => 'halo']
        );
    }

    public function test_nomor_istimewa_tidak_ikut_dilepas_saat_workspace_ditangguhkan(): void
    {
        $this->daftarkanIstimewa();
        $this->berlangganan($this->pelanggan, 'prime');

        $istimewa = $this->sesi($this->pelanggan, '6282318280376');
        $biasa = $this->sesi($this->pelanggan, '6289999999999', 'CS pelanggan');

        $langganan = $this->pelanggan->fresh()->subscription;
        $langganan->forceFill(['status' => 'past_due', 'past_due_at' => now()->subDays(30)])->save();

        app(SubscriptionService::class)->suspend($langganan->fresh());

        $this->assertSame('connected', $istimewa->fresh()->status, 'Nomor perusahaan ikut dilepas.');
        $this->assertSame('disconnected', $biasa->fresh()->status);
    }

    // ================= Akun bebas =================

    public function test_akun_bebas_membebaskan_seluruh_workspace_miliknya(): void
    {
        $this->pemilik->forceFill(['is_exempt' => true])->save();

        $workspace = $this->pelanggan->fresh();
        $workspace->forceFill(['status' => 'suspended'])->save();

        $this->assertTrue($workspace->fresh()->isExempt());
        $this->assertTrue($workspace->fresh()->isActive(), 'Workspace bebas ikut mati karena kolom status.');

        // Termasuk workspace yang dibuat SETELAH pembebasan diberikan — itu
        // alasan penandanya di orangnya, bukan di workspace-nya.
        $baru = Workspace::create([
            'name' => 'Cabang Baru',
            'slug' => 'cabang-baru',
            'owner_id' => $this->pemilik->id,
            'owner_email' => $this->pemilik->email,
        ]);

        $this->assertTrue($baru->isExempt());
    }

    public function test_akun_bebas_tidak_dihalangi_middleware_langganan(): void
    {
        $this->pemilik->forceFill(['is_exempt' => true])->save();
        $this->pelanggan->forceFill(['status' => 'suspended'])->save();

        $this->actingAs($this->pemilik)
            ->post(route('sessions.store'), ['name' => 'CS'])
            ->assertRedirect(route('sessions.index'));

        $this->assertSame(1, $this->pelanggan->sessions()->count());
    }

    public function test_akun_biasa_tetap_ditagih(): void
    {
        // Diberi langganan lebih dulu: tanpa itu `ensureFor()` di middleware
        // membuatkan paket coba gratis dan menghidupkan workspace-nya kembali —
        // yang benar, tapi bukan keadaan yang sedang diuji di sini.
        $this->berlangganan($this->pelanggan, 'prime');
        $this->pelanggan->fresh()->subscription->forceFill(['status' => 'past_due'])->save();
        $this->pelanggan->forceFill(['status' => 'suspended'])->save();

        $this->assertFalse($this->pelanggan->fresh()->isExempt());

        $this->actingAs($this->pemilik)
            ->post(route('sessions.store'), ['name' => 'CS'])
            ->assertRedirect();

        $this->assertSame(0, $this->pelanggan->sessions()->count());
    }

    // ================= Pengirim pemberitahuan =================

    public function test_pengirim_pemberitahuan_dipilih_dari_panel_bukan_env(): void
    {
        config(['billing.notify_workspace_id' => null]);

        $pesanvo = Workspace::create(['name' => 'Pesanvo Notifikasi', 'slug' => 'pesanvo-notif']);
        $this->sesi($pesanvo, '6282318280376', 'Notifikasi');

        $notifier = app(WhatsAppNotifier::class);

        $this->assertFalse($notifier->ready(), 'Belum dipilih tapi sudah mengaku siap.');

        AppSetting::simpan('notify_workspace_id', $pesanvo->id);

        $this->assertTrue(app(WhatsAppNotifier::class)->ready());
    }

    // ================= Panel admin =================

    public function test_super_admin_bisa_mengelola_pengecualian(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@pesanvo.id',
            'password' => Hash::make('rahasia12345'),
            'is_super_admin' => true,
        ]);

        $this->actingAs($admin)->get(route('admin.exemptions'))->assertOk();

        // Nomor ditulis dalam format 08xx dan harus tersimpan ternormalisasi —
        // kalau tidak, pencocokannya meleset dan pengecualiannya diam-diam mati.
        $this->actingAs($admin)->post(route('admin.exemptions.numbers.store'), [
            'phone' => '082318280376',
            'label' => 'Nomor perusahaan',
        ])->assertRedirect();

        $this->assertDatabaseHas('special_numbers', ['phone' => '6282318280376']);
        $this->assertTrue(SpecialNumber::cocok('082318280376'));
        $this->assertTrue(SpecialNumber::cocok('+62 823-1828-0376'));

        // Ganda ditolak, supaya tidak ada dua baris mengatur nomor yang sama
        // dengan keterangan berbeda.
        $this->actingAs($admin)->post(route('admin.exemptions.numbers.store'), [
            'phone' => '6282318280376',
            'label' => 'Duplikat',
        ])->assertSessionHasErrors('phone');

        $this->actingAs($admin)
            ->post(route('admin.exemptions.users.toggle', $this->pemilik->id))
            ->assertRedirect();

        $this->assertTrue($this->pemilik->fresh()->is_exempt);
    }

    public function test_bukan_super_admin_tidak_bisa_membuka_pengecualian(): void
    {
        // 404, bukan 403: bagi yang bukan super admin, panel ini sebaiknya tidak
        // tampak pernah ada.
        $this->actingAs($this->pemilik)->get(route('admin.exemptions'))->assertNotFound();
    }

    /**
     * Tes kirim menjelaskan langkah mana yang belum selesai, bukan cuma gagal.
     *
     * "Tidak siap" bisa berarti tiga hal yang butuh tindakan berbeda: pengirim
     * belum dipilih, sudah dipilih tapi nomornya belum discan, atau engine
     * sedang mati. Menebaknya sendiri adalah pekerjaan yang tidak perlu ada.
     */
    public function test_tes_kirim_menyebut_langkah_yang_kurang(): void
    {
        config(['billing.notify_workspace_id' => null]);
        $notifier = app(WhatsAppNotifier::class);

        // 1. Belum ada pengirim.
        $hasil = $notifier->kirimTes('085774410978');
        $this->assertFalse($hasil['berhasil']);
        $this->assertStringContainsString('belum dipilih', $hasil['pesan']);

        // 2. Pengirim ada, sesinya ada, tapi belum tersambung.
        $pesanvo = Workspace::create(['name' => 'Pesanvo Notifikasi', 'slug' => 'pesanvo-notif']);
        $sesi = $pesanvo->sessions()->create(['name' => 'Notifikasi', 'status' => 'pending', 'driver' => 'wwebjs']);
        AppSetting::simpan('notify_workspace_id', $pesanvo->id);

        $hasil = app(WhatsAppNotifier::class)->kirimTes('085774410978');
        $this->assertFalse($hasil['berhasil']);
        $this->assertStringContainsString('belum tersambung', $hasil['pesan']);

        // 3. Nomor tersambung — pesannya benar-benar diantrekan.
        $sesi->update(['status' => 'connected', 'phone_number' => '6282318280376', 'connected_at' => now()]);

        $hasil = app(WhatsAppNotifier::class)->kirimTes('085774410978');

        $this->assertTrue($hasil['berhasil'], $hasil['pesan']);

        // Nomornya dinormalkan sebelum dikirim: 085xxx tidak dikenali WhatsApp.
        $this->assertDatabaseHas('messages', [
            'workspace_id' => $pesanvo->id,
            'to_number' => '6285774410978',
            'direction' => 'outbound',
        ]);
    }

    /** Nomor tujuan yang tidak masuk akal ditolak sebelum menyentuh engine. */
    public function test_tes_kirim_menolak_nomor_yang_tidak_dikenali(): void
    {
        $hasil = app(WhatsAppNotifier::class)->kirimTes('halo');

        $this->assertFalse($hasil['berhasil']);
        $this->assertStringContainsString('tidak dikenali', $hasil['pesan']);
    }
}
