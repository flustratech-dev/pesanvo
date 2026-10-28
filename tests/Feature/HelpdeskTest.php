<?php

namespace Tests\Feature;

use App\Mail\TiketDibalas;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\SubscriptionService;
use App\Services\Notifications\HelpdeskMessages;
use App\Services\Notifications\WhatsAppNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Helpdesk yang dibangun di dalam produk ini sendiri.
 *
 * Tes pertama yang paling penting: **pelanggan dengan langganan mati tetap bisa
 * membuat dan membalas tiket.** Rutenya sengaja di luar middleware
 * `subscription`, dan kalau suatu saat ia dipindahkan ke dalam grup itu, seluruh
 * maksud fitur ini hilang tanpa satu pun galat yang terlihat — pelanggan yang
 * layanannya berhenti justru yang paling butuh menghubungi kami.
 */
class HelpdeskTest extends TestCase
{
    use RefreshDatabase;

    private User $pelanggan;

    private User $admin;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        Mail::fake();
        Storage::fake('media');

        // Suite berjalan dengan MAIL_MAILER=array, dan `EmailNotifier::ready()`
        // menolak itu sama seperti ia menolak `log` — tanpa baris ini, tes
        // notifikasi email di bawah menguji jalur "email belum dikonfigurasi".
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp-relay.brevo.com',
            'mail.from.address' => 'admin@gmail.com',
        ]);

        $this->pelanggan = User::create([
            'name' => 'Owner',
            'email' => 'budi@contoh.id',
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
            'owner_id' => $this->pelanggan->id,
            'owner_email' => $this->pelanggan->email,
            'billing_phone' => '6281234567890',
            'billing_email' => 'keuangan@contoh.id',
            'max_sessions' => 1,
            'monthly_message_quota' => 1000,
        ]);

        $this->workspace->members()->attach($this->pelanggan->id, ['role' => 'owner']);
    }

    private function sebagaiPelanggan(): self
    {
        $this->actingAs($this->pelanggan)
            ->withSession(['current_workspace_id' => $this->workspace->id]);

        return $this;
    }

    /** Mematikan langganan lewat jalur resminya, bukan dengan menulis kolom. */
    private function matikanLangganan(): void
    {
        $subscriptions = app(SubscriptionService::class);
        $subscription = $subscriptions->ensureFor($this->workspace);

        $subscription->forceFill(['current_period_end' => now()->subDays(5)])->save();
        $subscriptions->markPastDue($subscription);

        $this->workspace->refresh();

        // Kalau ini gagal, seluruh tes di bawah menguji workspace yang sehat.
        $this->assertFalse($this->workspace->isActive(), 'Langganan seharusnya sudah mati.');
    }

    private function buatTiket(): Ticket
    {
        $this->sebagaiPelanggan()->post(route('tickets.store'), [
            'subject' => 'Nomor terputus terus',
            'category' => 'nomor',
            'priority' => 'high',
            'body' => 'Sudah scan QR tiga kali tapi selalu terputus lagi.',
        ]);

        return Ticket::latest('id')->firstOrFail();
    }

    // ===================== Yang paling penting =====================

    /**
     * Definisi selesai #1.
     *
     * Menutup helpdesk untuk pelanggan yang layanannya mati adalah kesalahan
     * yang paling mahal: satu-satunya jalur yang tersisa jadi mencari nomor
     * kami sendiri, dan yang tidak menemukannya berhenti jadi pelanggan tanpa
     * pernah bilang kenapa.
     */
    public function test_pelanggan_dengan_langganan_mati_tetap_bisa_membuat_tiket(): void
    {
        $this->matikanLangganan();

        $this->sebagaiPelanggan()->post(route('tickets.store'), [
            'subject' => 'Layanan saya berhenti',
            'category' => 'tagihan',
            'body' => 'Saya sudah transfer kemarin tapi belum aktif.',
        ])->assertRedirect();

        $this->assertSame(1, Ticket::count());
    }

    public function test_pelanggan_dengan_langganan_mati_tetap_bisa_membalas(): void
    {
        $ticket = $this->buatTiket();
        $this->matikanLangganan();

        $this->sebagaiPelanggan()
            ->post(route('tickets.reply', $ticket->id), ['body' => 'Masih belum bisa.'])
            ->assertRedirect();

        $this->assertSame(2, $ticket->fresh()->messages()->count());
    }

    /**
     * Penjagaan struktural, bukan perilaku.
     *
     * Tes di atas bisa tetap hijau kalau `EnsureSubscriptionActive` suatu saat
     * dilonggarkan karena alasan lain. Yang benar-benar dijaga di sini adalah
     * rute bantuan TIDAK PERNAH ikut middleware itu — kalau seseorang
     * memindahkannya ke dalam grup, ini yang jatuh lebih dulu dan menyebut
     * alasannya.
     */
    public function test_rute_bantuan_tidak_pernah_tunduk_pada_middleware_langganan(): void
    {
        foreach (['tickets.index', 'tickets.store', 'tickets.reply', 'tickets.close'] as $nama) {
            $rute = app('router')->getRoutes()->getByName($nama);

            $this->assertNotNull($rute, "Rute {$nama} tidak ada.");
            $this->assertNotContains(
                'subscription',
                $rute->gatherMiddleware(),
                "Rute {$nama} tunduk pada middleware langganan — pelanggan yang layanannya mati "
                    .'tidak akan bisa menghubungi kami sama sekali.',
            );
        }
    }

    // ===================== Notifikasi =====================

    /** Definisi selesai #2. */
    public function test_admin_dikabari_saat_ada_tiket_baru(): void
    {
        config(['billing.admin_phone' => '6289999999999']);

        $this->mock(WhatsAppNotifier::class, function ($mock) {
            $mock->shouldReceive('toAdmin')->once();
            $mock->shouldReceive('toWorkspace')->zeroOrMoreTimes();
        });

        $this->buatTiket();
    }

    /** Definisi selesai #2, bagian kedua: balasan pelanggan juga mengabari tim. */
    public function test_admin_dikabari_saat_pelanggan_membalas(): void
    {
        $ticket = $this->buatTiket();

        $this->mock(WhatsAppNotifier::class, function ($mock) {
            $mock->shouldReceive('toAdmin')->once();
            $mock->shouldReceive('toWorkspace')->zeroOrMoreTimes();
        });

        $this->sebagaiPelanggan()->post(route('tickets.reply', $ticket->id), ['body' => 'Halo?']);
    }

    /** Definisi selesai #3. */
    public function test_pelanggan_dikabari_saat_dibalas(): void
    {
        $ticket = $this->buatTiket();

        $this->mock(WhatsAppNotifier::class, function ($mock) {
            $mock->shouldReceive('toWorkspace')->once();
            $mock->shouldReceive('toAdmin')->zeroOrMoreTimes();
        });

        $this->actingAs($this->admin)->post(route('admin.tickets.reply', $ticket->id), [
            'body' => 'Sudah kami periksa, silakan coba lagi.',
        ])->assertRedirect();

        Mail::assertSent(TiketDibalas::class, fn ($surat) => $surat->hasTo('keuangan@contoh.id'));
    }

    /**
     * Isi balasan tidak pernah ikut keluar lewat WhatsApp maupun email.
     *
     * Tiket bisa memuat apa saja yang ditempelkan pelanggan saat melaporkan
     * masalah — termasuk kunci API mereka sendiri — dan keduanya diteruskan
     * serta diarsipkan di tempat yang tidak kami kendalikan.
     */
    public function test_isi_balasan_tidak_ikut_dikirim_keluar(): void
    {
        $ticket = $this->buatTiket();
        $rahasia = 'vwa_rahasiasekali.JANGANBOCOR';

        $pesanWa = HelpdeskMessages::balasanUntukPelanggan($ticket);
        $this->assertStringNotContainsString($rahasia, $pesanWa);

        $this->actingAs($this->admin)->post(route('admin.tickets.reply', $ticket->id), [
            'body' => "Kunci Anda {$rahasia} sudah kami cabut.",
        ]);

        Mail::assertSent(TiketDibalas::class, function ($surat) use ($rahasia) {
            return ! str_contains($surat->render(), $rahasia);
        });
    }

    // ===================== Lampiran =====================

    /** Definisi selesai #4. */
    public function test_lampiran_tidak_bisa_dibuka_workspace_lain(): void
    {
        $this->sebagaiPelanggan()->post(route('tickets.store'), [
            'subject' => 'Ada galat',
            'category' => 'teknis',
            'body' => 'Terlampir tangkapan layarnya.',
            'lampiran' => UploadedFile::fake()->image('galat.png'),
        ]);

        $ticket = Ticket::latest('id')->firstOrFail();
        $pesan = $ticket->messages()->firstOrFail();

        $this->assertNotNull($pesan->attachment_path);
        Storage::disk('media')->assertExists($pesan->attachment_path);

        // Pemiliknya boleh.
        $this->sebagaiPelanggan()
            ->get(route('tickets.attachment', [$ticket->id, $pesan->id]))
            ->assertOk();

        // Orang lain dengan workspace-nya sendiri: tidak.
        $penyusup = User::create([
            'name' => 'Orang Lain',
            'email' => 'lain@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $lain = Workspace::create([
            'name' => 'Workspace Lain',
            'slug' => 'workspace-lain',
            'owner_id' => $penyusup->id,
            'owner_email' => $penyusup->email,
            'max_sessions' => 1,
            'monthly_message_quota' => 1000,
        ]);

        $lain->members()->attach($penyusup->id, ['role' => 'owner']);

        $this->actingAs($penyusup)
            ->withSession(['current_workspace_id' => $lain->id])
            ->get(route('tickets.attachment', [$ticket->id, $pesan->id]))
            ->assertNotFound();
    }

    public function test_tiket_workspace_lain_tidak_bisa_dibuka(): void
    {
        $ticket = $this->buatTiket();

        $penyusup = User::create([
            'name' => 'Orang Lain',
            'email' => 'lain@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $lain = Workspace::create([
            'name' => 'Workspace Lain',
            'slug' => 'workspace-lain',
            'owner_id' => $penyusup->id,
            'owner_email' => $penyusup->email,
            'max_sessions' => 1,
            'monthly_message_quota' => 1000,
        ]);

        $lain->members()->attach($penyusup->id, ['role' => 'owner']);

        $this->actingAs($penyusup)
            ->withSession(['current_workspace_id' => $lain->id])
            ->get(route('tickets.show', $ticket->id))
            ->assertNotFound();
    }

    public function test_lampiran_berjenis_terlarang_ditolak(): void
    {
        $this->sebagaiPelanggan()->post(route('tickets.store'), [
            'subject' => 'Coba unggah',
            'category' => 'teknis',
            'body' => 'Terlampir.',
            'lampiran' => UploadedFile::fake()->create('jahat.php', 10),
        ])->assertSessionHasErrors('lampiran');

        $this->assertSame(0, Ticket::count());
    }

    // ===================== Alur status =====================

    /**
     * Tanpa ini, tiket yang sudah dijawab lalu dibalas lagi hilang dari saringan
     * "perlu dijawab" dan tidak ada yang tahu masih ada yang menunggu.
     */
    public function test_balasan_pelanggan_mengembalikan_tiket_ke_perlu_dijawab(): void
    {
        $ticket = $this->buatTiket();

        $this->actingAs($this->admin)->post(route('admin.tickets.reply', $ticket->id), [
            'body' => 'Sudah kami periksa.',
        ]);

        $this->assertSame('answered', $ticket->fresh()->status);

        $this->sebagaiPelanggan()->post(route('tickets.reply', $ticket->id), ['body' => 'Masih belum.']);

        $this->assertSame('open', $ticket->fresh()->status);
        $this->assertTrue($ticket->fresh()->perluDijawab());
    }

    public function test_admin_bisa_membalas_sekaligus_menutup(): void
    {
        $ticket = $this->buatTiket();

        $this->actingAs($this->admin)->post(route('admin.tickets.reply', $ticket->id), [
            'body' => 'Sudah beres.',
            'tutup' => '1',
        ])->assertRedirect();

        $this->assertSame('closed', $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->closed_at);
    }

    public function test_saringan_bawaan_admin_adalah_perlu_dijawab(): void
    {
        $terbuka = $this->buatTiket();

        // Satu tiket yang sudah dijawab: tidak boleh muncul di saringan bawaan.
        $this->actingAs($this->admin)->post(route('admin.tickets.reply', $terbuka->id), ['body' => 'Dijawab.']);

        $this->sebagaiPelanggan()->post(route('tickets.store'), [
            'subject' => 'Pertanyaan kedua yang belum dijawab',
            'category' => 'api',
            'body' => 'Bagaimana cara memakai webhook?',
        ]);

        $daftar = $this->actingAs($this->admin)->get(route('admin.tickets'))->assertOk();

        // Isi halamannya saja: lonceng di bilah atas memang menyebut judul
        // tiket yang baru masuk, termasuk yang sudah dijawab.
        $isi = $this->isiUtama($daftar);

        $this->assertStringContainsString('Pertanyaan kedua yang belum dijawab', $isi);
        $this->assertStringNotContainsString('Nomor terputus terus', $isi);
    }

    public function test_halaman_pelanggan_dan_admin_terbuka(): void
    {
        $ticket = $this->buatTiket();

        $this->sebagaiPelanggan()->get(route('tickets.index'))->assertOk()->assertSee('Nomor terputus terus');
        $this->sebagaiPelanggan()->get(route('tickets.show', $ticket->id))->assertOk();

        $this->actingAs($this->admin)->get(route('admin.tickets.show', $ticket->id))
            ->assertOk()
            ->assertSee('Toko Owner');
    }
}
