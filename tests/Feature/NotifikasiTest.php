<?php

namespace Tests\Feature;

use App\Jobs\PantauKesehatanJob;
use App\Models\Notification;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\SubscriptionService;
use App\Services\HelpdeskService;
use App\Services\Notifications\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Pusat notifikasi — dua aliran yang tidak pernah bercampur.
 *
 * Dua hal yang paling mahal kalau rusak, dan keduanya dijaga di sini:
 *
 * 1. **Kabar tim bocor ke pelanggan.** Isinya menyebut nama workspace dan
 *    nominal tagihan orang lain. `?audience=admin` di URL harus tidak cukup
 *    untuk membacanya.
 * 2. **Kabar kembar.** Job harian bisa berjalan dua kali dan penjadwal
 *    memeriksa kesehatan tiap jam. Lonceng yang terisi tiga puluh baris sama
 *    berhenti dibaca siapa pun — lebih buruk daripada tidak ada notifikasi.
 */
class NotifikasiTest extends TestCase
{
    use RefreshDatabase;

    private User $pemilik;

    private User $anggota;

    private User $admin;

    private Workspace $workspace;

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

        $this->anggota = User::create([
            'name' => 'Anggota Tim',
            'email' => 'anggota@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->admin = User::create([
            'name' => 'Pesanvo Finance',
            'email' => 'finance@pesanvo.id',
            'password' => Hash::make('rahasia12345'),
            'is_super_admin' => true,
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Toko Owner',
            'slug' => 'toko-ryan',
            'owner_id' => $this->pemilik->id,
            'owner_email' => $this->pemilik->email,
            'billing_phone' => '6281234567890',
            'max_sessions' => 1,
            'monthly_message_quota' => 1000,
        ]);

        $this->workspace->members()->attach([
            $this->pemilik->id => ['role' => 'owner'],
            $this->anggota->id => ['role' => 'member'],
        ]);
    }

    // ===================== Penyebaran =====================

    /**
     * Ke SELURUH anggota, bukan cuma pemiliknya.
     *
     * Yang memegang integrasi sering bukan yang membayar, dan nomor yang
     * terputus perlu diketahui yang menjaganya.
     */
    public function test_kabar_workspace_sampai_ke_seluruh_anggota(): void
    {
        app(Notifier::class)->keWorkspace(
            workspace: $this->workspace,
            type: 'session.disconnected',
            title: 'Nomor Anda terputus',
        );

        $this->assertSame(2, Notification::count());
        $this->assertSame(1, Notification::where('user_id', $this->pemilik->id)->count());
        $this->assertSame(1, Notification::where('user_id', $this->anggota->id)->count());
    }

    public function test_kabar_tim_hanya_sampai_ke_super_admin(): void
    {
        app(Notifier::class)->keAdmin(type: 'ticket.new', title: 'Tiket baru');

        $this->assertSame(1, Notification::count());
        $this->assertSame($this->admin->id, Notification::firstOrFail()->user_id);
        $this->assertSame('admin', Notification::firstOrFail()->audience);
    }

    // ===================== Kabar kembar =====================

    /**
     * Ditegakkan indeks unik di database, bukan cuma pemeriksaan di kode: dua
     * worker bisa memprosesnya bersamaan.
     */
    public function test_penanda_yang_sama_tidak_menghasilkan_dua_baris(): void
    {
        $notifier = app(Notifier::class);

        foreach (range(1, 5) as $ke) {
            $notifier->keWorkspace(
                workspace: $this->workspace,
                type: 'quota.low',
                title: 'Kuota menipis',
                dedupe: 'quota:hampir:2026-09',
            );
        }

        // Dua anggota, masing-masing satu kali — bukan sepuluh.
        $this->assertSame(2, Notification::count());
    }

    /** Tanpa penanda, kabar memang boleh berulang — mis. tiap tiket baru. */
    public function test_tanpa_penanda_kabar_boleh_berulang(): void
    {
        $notifier = app(Notifier::class);

        foreach (range(1, 3) as $ke) {
            $notifier->keAdmin(type: 'ticket.new', title: "Tiket baru #{$ke}");
        }

        $this->assertSame(3, Notification::count());
    }

    /**
     * Kunci yang sama untuk orang berbeda tetap lolos — indeksnya
     * `(user_id, dedupe_key)`, bukan `dedupe_key` saja. Kalau salah, hanya
     * anggota pertama yang menerima kabarnya dan sisanya diam.
     */
    public function test_penanda_sama_tetap_sampai_ke_tiap_anggota(): void
    {
        app(Notifier::class)->keWorkspace(
            workspace: $this->workspace,
            type: 'balance.low',
            title: 'Saldo menipis',
            dedupe: 'balance:hampir:1',
        );

        $this->assertSame(2, Notification::count());
    }

    // ===================== Pemisahan aliran =====================

    /**
     * Inti keamanan fitur ini: kabar tim menyebut nama workspace dan nominal
     * tagihan pelanggan lain.
     */
    public function test_bukan_admin_tidak_bisa_membaca_aliran_tim(): void
    {
        app(Notifier::class)->keAdmin(
            type: 'invoice.proof',
            title: 'Bukti bayar RAHASIA-INV-42',
        );

        app(Notifier::class)->keWorkspace(
            workspace: $this->workspace,
            type: 'session.disconnected',
            title: 'Nomor Anda terputus',
        );

        $this->actingAs($this->pemilik)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('notifications.index', ['audience' => 'admin']))
            ->assertOk()
            ->assertDontSee('RAHASIA-INV-42')
            ->assertSee('Nomor Anda terputus');
    }

    public function test_admin_bisa_berpindah_antar_aliran(): void
    {
        app(Notifier::class)->keAdmin(type: 'invoice.proof', title: 'Bukti bayar baru');

        $this->actingAs($this->admin)
            ->get(route('notifications.index', ['audience' => 'admin']))
            ->assertOk()
            ->assertSee('Bukti bayar baru');
    }

    /** Notifikasi milik orang lain tidak bisa dibuka walau id-nya ditebak. */
    public function test_notifikasi_orang_lain_tidak_bisa_dibuka(): void
    {
        app(Notifier::class)->keAdmin(type: 'invoice.proof', title: 'Bukti bayar baru');

        $milikAdmin = Notification::firstOrFail();

        $this->actingAs($this->pemilik)
            ->get(route('notifications.open', $milikAdmin->id))
            ->assertNotFound();

        $this->assertNull($milikAdmin->fresh()->read_at);
    }

    // ===================== Sudah dibaca =====================

    public function test_membuka_notifikasi_menandainya_dibaca_lalu_mengarahkan(): void
    {
        app(Notifier::class)->keWorkspace(
            workspace: $this->workspace,
            type: 'session.disconnected',
            title: 'Nomor terputus',
            url: '/sessions',
        );

        $n = Notification::where('user_id', $this->pemilik->id)->firstOrFail();

        $this->actingAs($this->pemilik)
            ->get(route('notifications.open', $n->id))
            ->assertRedirect('/sessions');

        $this->assertNotNull($n->fresh()->read_at);
    }

    /**
     * URL yang tersimpan dari callback engine (host 127.0.0.1) atau sisa database
     * lama harus dialihkan ke path relatif, bukan mengirim browser pengguna ke
     * localhost mesin lokal mereka sendiri.
     */
    public function test_membuka_notifikasi_dengan_url_loopback_mengalihkan_ke_path_relatif(): void
    {
        // Masukkan langsung ke DB seolah-olah data lama produksi sebelum perbaikan
        $n = Notification::forceCreate([
            'user_id' => $this->pemilik->id,
            'workspace_id' => $this->workspace->id,
            'audience' => 'workspace',
            'type' => 'session.disconnected',
            'level' => 'danger',
            'title' => 'Nomor terputus',
            'url' => 'http://127.0.0.1/sessions',
        ]);

        $this->actingAs($this->pemilik)
            ->get(route('notifications.open', $n->id))
            ->assertRedirect('/sessions');

        $this->assertNotNull($n->fresh()->read_at);
    }

    public function test_membuka_notifikasi_dengan_url_localhost_mengalihkan_ke_path_relatif(): void
    {
        $n = Notification::forceCreate([
            'user_id' => $this->pemilik->id,
            'workspace_id' => $this->workspace->id,
            'audience' => 'workspace',
            'type' => 'balance.low',
            'level' => 'warning',
            'title' => 'Saldo menipis',
            'url' => 'http://localhost/balance?status=low#topup',
        ]);

        $this->actingAs($this->pemilik)
            ->get(route('notifications.open', $n->id))
            ->assertRedirect('/balance?status=low#topup');
    }

    public function test_notifier_membersihkan_url_loopback_sebelum_disimpan(): void
    {
        app(Notifier::class)->keWorkspace(
            workspace: $this->workspace,
            type: 'session.disconnected',
            title: 'Nomor terputus',
            url: 'http://127.0.0.1:8000/sessions',
        );

        $n = Notification::where('user_id', $this->pemilik->id)->latest('id')->firstOrFail();

        $this->assertSame('/sessions', $n->url);
    }

    public function test_tandai_semua_dibaca_hanya_menyentuh_aliran_yang_dilihat(): void
    {
        $notifier = app(Notifier::class);

        $notifier->keWorkspace(workspace: $this->workspace, type: 'a', title: 'Kabar workspace');
        $notifier->keAdmin(type: 'b', title: 'Kabar tim');

        $this->actingAs($this->admin)
            ->post(route('notifications.read-all', ['audience' => 'admin']))
            ->assertRedirect();

        $this->assertSame(0, Notification::untuk($this->admin, 'admin')->belumDibaca()->count());
        // Aliran pelanggan milik pemilik tidak ikut tersentuh.
        $this->assertSame(1, Notification::untuk($this->pemilik, 'workspace')->belumDibaca()->count());
    }

    /**
     * Yang BELUM dibaca tidak pernah dipangkas berapa pun umurnya.
     *
     * Kabar yang hilang sebelum sempat dilihat adalah persis kegagalan yang
     * lonceng ini dibuat untuk mencegahnya.
     */
    public function test_pemangkasan_tidak_pernah_membuang_yang_belum_dibaca(): void
    {
        $notifier = app(Notifier::class);

        $notifier->keUser($this->pemilik, 'lama.belum', 'Belum dibaca, tapi lama');
        $notifier->keUser($this->pemilik, 'lama.sudah', 'Sudah dibaca dan lama');

        Notification::query()->update(['created_at' => now()->subYear()]);
        Notification::where('type', 'lama.sudah')->update(['read_at' => now()->subYear()]);

        $notifier->pangkas(90);

        $this->assertSame(1, Notification::count());
        $this->assertSame('lama.belum', Notification::firstOrFail()->type);
    }

    // ===================== Tersambung ke peristiwa nyata =====================

    public function test_tiket_baru_mengabari_tim_dan_balasan_mengabari_pelanggan(): void
    {
        $helpdesk = app(HelpdeskService::class);

        $ticket = $helpdesk->buatTiket($this->workspace, $this->pemilik, [
            'subject' => 'Nomor terputus',
            'category' => 'nomor',
            'body' => 'Sudah scan tiga kali.',
        ]);

        $this->assertSame(1, Notification::where('type', 'ticket.new')->count());
        $this->assertSame('admin', Notification::where('type', 'ticket.new')->firstOrFail()->audience);

        $helpdesk->balasAdmin($ticket, 'Sudah kami periksa.');

        // Kedua anggota workspace dikabari.
        $this->assertSame(2, Notification::where('type', 'ticket.answered')->count());
    }

    public function test_tagihan_terbit_dan_lunas_masuk_lonceng_pelanggan(): void
    {
        $subscriptions = app(SubscriptionService::class);

        $invoice = $subscriptions->issueInvoice($this->workspace, 'prime', 'monthly');
        $this->assertSame(2, Notification::where('type', 'invoice.issued')->count());

        $subscriptions->markPaid($invoice);
        $this->assertSame(2, Notification::where('type', 'invoice.paid')->count());
    }

    // ===================== Pengawas kesehatan =====================

    /**
     * Seluruh keadaan ini sudah terlihat di /admin/sistem — tapi hanya oleh
     * yang kebetulan membukanya, dan tidak ada yang membuka halaman apa pun
     * saat engine mati jam tiga pagi.
     */
    public function test_pengawas_mengabari_tim_saat_ada_yang_rusak_tanpa_gejala(): void
    {
        // `Http::fake()` di setUp mendaftarkan catch-all 200, dan stub yang
        // didaftarkan SESUDAHNYA tidak pernah menang atasnya. Factory-nya
        // ditukar supaya engine benar-benar terbaca mati.
        Http::swap(new Factory);
        Http::fake(['*' => Http::response('', 500)]);
        config(['mail.default' => 'log']);

        app()->call([new PantauKesehatanJob, 'handle']);

        $jenis = Notification::where('audience', 'admin')->pluck('type')->all();

        $this->assertContains('sistem.engine_mati', $jenis);
        $this->assertContains('sistem.email_mati', $jenis);
        $this->assertContains('sistem.pengirim_mati', $jenis);
    }

    /**
     * Berjalan tiap jam, jadi tanpa penanda harian engine yang mati semalaman
     * menghasilkan dua belas baris yang sama.
     */
    public function test_pengawas_yang_berjalan_berulang_tidak_menggandakan_kabar(): void
    {
        Http::swap(new Factory);
        Http::fake(['*' => Http::response('', 500)]);

        foreach (range(1, 5) as $ke) {
            app()->call([new PantauKesehatanJob, 'handle']);
        }

        $this->assertSame(1, Notification::where('type', 'sistem.engine_mati')->count());
    }

    public function test_pengawas_diam_saat_semuanya_sehat(): void
    {
        Http::swap(new Factory);
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp-relay.brevo.com',
            'mail.from.address' => 'admin@gmail.com',
        ]);

        // Sesi pengirim siap.
        $pesanvo = Workspace::create([
            'name' => 'Pesanvo Notifikasi',
            'slug' => 'pesanvo-notifikasi',
            'owner_id' => $this->admin->id,
            'owner_email' => $this->admin->email,
            'max_sessions' => 1,
            'monthly_message_quota' => 0,
        ]);

        $pesanvo->sessions()->create([
            'name' => 'Nomor Pesanvo',
            'status' => 'connected',
            'driver' => 'wwebjs',
        ]);

        config(['billing.notify_workspace_id' => $pesanvo->id]);

        app()->call([new PantauKesehatanJob, 'handle']);

        foreach (['sistem.engine_mati', 'sistem.email_mati', 'sistem.pengirim_mati'] as $jenis) {
            $this->assertSame(0, Notification::where('type', $jenis)->count(), "{$jenis} muncul padahal sehat.");
        }
    }

    // ===================== Lonceng di layar =====================

    public function test_lonceng_menampilkan_jumlah_yang_belum_dibaca(): void
    {
        app(Notifier::class)->keWorkspace(
            workspace: $this->workspace,
            type: 'session.disconnected',
            title: 'Nomor terputus',
        );

        $this->actingAs($this->pemilik)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Nomor terputus');
    }

    /** Kabar yang gagal dibuat tidak boleh menjatuhkan apa yang memanggilnya. */
    public function test_notifikasi_gagal_tidak_menjatuhkan_pemanggilnya(): void
    {
        $ticket = app(HelpdeskService::class)->buatTiket($this->workspace, $this->pemilik, [
            'subject' => 'Uji',
            'category' => 'teknis',
            'body' => 'Isi.',
        ]);

        // Tabelnya dibuang sebentar supaya penulisannya PASTI gagal di kedua
        // driver. Judul yang kepanjangan tidak cukup: SQLite tidak menegakkan
        // panjang kolom sama sekali, jadi tesnya lulus tanpa menguji apa pun.
        Schema::drop('notifications');

        $hasil = app(Notifier::class)->keWorkspace(
            workspace: $this->workspace,
            type: 'uji.gagal',
            title: 'Kabar yang tidak bisa ditulis',
        );

        $this->assertSame(0, $hasil, 'Notifier seharusnya menelan galatnya, bukan meneruskannya.');
        $this->assertInstanceOf(Ticket::class, $ticket->fresh());
    }
}
