<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\Workspace;
use App\Support\Pemangkas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Retensi berjenjang: data teknis dipangkas agresif, data bisnis tidak.
 *
 * Yang paling berbahaya di kelas ini bukan pemangkas yang gagal menghapus —
 * itu cuma tabel yang membengkak. Yang berbahaya adalah pemangkas yang
 * menghapus terlalu banyak: riwayat pesan yang dibayar pelanggan, catatan
 * keuangan, atau kabar yang belum sempat dibaca. Penghapusan tidak bisa
 * dibatalkan, jadi arah itu yang paling banyak diuji di sini.
 */
class PemangkasanTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $pengguna;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pengguna = User::create([
            'name' => 'Owner',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Contoh',
            'slug' => 'contoh',
            'owner_id' => $this->pengguna->id,
            'owner_email' => $this->pengguna->email,
            'max_sessions' => 1,
            'plan_slug' => 'essentials',
        ]);
    }

    /**
     * `created_at` bukan kolom fillable di mana pun, jadi baris yang dibuat
     * lewat `create()` selalu lahir dengan waktu sekarang. Umurnya dipaksa
     * langsung lewat query builder — kalau tidak, seluruh uji di bawah menguji
     * baris yang baru saja dibuat dan lulus tanpa membuktikan apa pun.
     */
    private function tuakan(string $tabel, mixed $id, string $waktu, array $tambahan = []): void
    {
        DB::table($tabel)->where('id', $id)->update(array_merge([
            'created_at' => $waktu,
            'updated_at' => $waktu,
        ], $tambahan));
    }

    private function kirimanWebhook(int $jumlah, array $atribut = []): void
    {
        $webhook = $this->workspace->webhooks()->create([
            'url' => 'https://contoh.id/hook',
            'secret' => str_repeat('a', 32),
        ]);

        $umur = $atribut['created_at'] ?? now()->subDays(30);
        unset($atribut['created_at'], $atribut['updated_at']);

        for ($i = 0; $i < $jumlah; $i++) {
            $baris = WebhookDelivery::create(array_merge([
                'webhook_id' => $webhook->id,
                'event' => 'message.status',
                'payload' => ['a' => 1],
            ], $atribut));

            $this->tuakan('webhook_deliveries', $baris->id, $umur);
        }
    }

    public function test_dry_run_tidak_menghapus_satu_baris_pun(): void
    {
        $this->kirimanWebhook(5);

        $hasil = (new Pemangkas)->jalankan(dryRun: true);

        $this->assertSame(5, $hasil['webhook_deliveries']);
        $this->assertSame(5, WebhookDelivery::count(), 'Mode kering menghapus sesuatu.');
    }

    public function test_kiriman_webhook_gagal_yang_tua_dihapus(): void
    {
        $this->kirimanWebhook(3);

        (new Pemangkas)->jalankan();

        $this->assertSame(0, WebhookDelivery::count());
    }

    public function test_kiriman_webhook_yang_masih_baru_dipertahankan(): void
    {
        $this->kirimanWebhook(3, ['created_at' => now()->subHours(2), 'updated_at' => now()->subHours(2)]);

        (new Pemangkas)->jalankan();

        $this->assertSame(3, WebhookDelivery::count(), 'Kiriman berumur dua jam masih dicari orang yang sedang men-debug.');
    }

    /**
     * Kiriman yang berhasil punya masa simpan jauh lebih pendek (48 jam)
     * daripada yang gagal (7 hari) — dan sebagian besar baris memang berhasil.
     */
    public function test_kiriman_berhasil_dipangkas_lebih_cepat_dari_yang_gagal(): void
    {
        $webhook = $this->workspace->webhooks()->create([
            'url' => 'https://contoh.id/hook',
            'secret' => str_repeat('a', 32),
        ]);

        $tiga = now()->subDays(3);

        $sukses = WebhookDelivery::create([
            'webhook_id' => $webhook->id, 'event' => 'a', 'payload' => [], 'delivered_at' => $tiga,
        ]);
        $gagal = WebhookDelivery::create([
            'webhook_id' => $webhook->id, 'event' => 'b', 'payload' => [], 'delivered_at' => null,
        ]);

        $this->tuakan('webhook_deliveries', $sukses->id, $tiga);
        $this->tuakan('webhook_deliveries', $gagal->id, $tiga);

        (new Pemangkas)->jalankan();

        $tersisa = WebhookDelivery::get();

        $this->assertCount(1, $tersisa);
        $this->assertNull($tersisa->first()->delivered_at, 'Yang tersisa seharusnya yang GAGAL.');
    }

    public function test_notifikasi_belum_dibaca_tidak_pernah_dibuang(): void
    {
        $lama = now()->subYears(2);

        $belum = Notification::create([
            'user_id' => $this->pengguna->id, 'audience' => 'workspace', 'type' => 'uji',
            'level' => 'info', 'title' => 'Belum dibaca',
        ]);
        $sudah = Notification::create([
            'user_id' => $this->pengguna->id, 'audience' => 'workspace', 'type' => 'uji2',
            'level' => 'info', 'title' => 'Sudah dibaca',
        ]);

        $this->tuakan('notifications', $belum->id, $lama, ['read_at' => null]);
        $this->tuakan('notifications', $sudah->id, $lama, ['read_at' => $lama]);

        (new Pemangkas)->jalankan();

        $tersisa = Notification::get();

        $this->assertCount(1, $tersisa);
        $this->assertSame('Belum dibaca', $tersisa->first()->title);
    }

    public function test_catatan_audit_setahun_terakhir_dipertahankan(): void
    {
        $baru = AuditLog::create(['action' => 'baru']);
        $lama = AuditLog::create(['action' => 'lama']);

        $this->tuakan('audit_logs', $baru->id, now()->subDays(300));
        $this->tuakan('audit_logs', $lama->id, now()->subDays(400));

        (new Pemangkas)->jalankan();

        $this->assertSame(['baru'], AuditLog::pluck('action')->all());
    }

    /**
     * Riwayat pesan adalah data yang DIBAYAR pelanggan, dan lamanya berbeda per
     * paket. Satu angka global akan menghapus riwayat 12 bulan milik Elite.
     */
    public function test_retensi_pesan_mengikuti_paket_bukan_satu_angka_global(): void
    {
        $elite = Workspace::create([
            'name' => 'Elite', 'slug' => 'elite',
            'owner_id' => $this->pengguna->id, 'owner_email' => $this->pengguna->email,
            'max_sessions' => 2, 'plan_slug' => 'elite',
        ]);

        foreach ([$this->workspace, $elite] as $ws) {
            $pesan = $ws->messages()->create([
                'direction' => 'outbound', 'to_number' => '628123456789', 'type' => 'text',
                'body' => 'halo', 'status' => 'sent',
            ]);

            $this->tuakan('messages', $pesan->id, now()->subDays(120));
        }

        (new Pemangkas)->jalankan();

        // Essentials menyimpan 30 hari: pesan 120 hari harus hilang.
        $this->assertSame(0, $this->workspace->messages()->count());
        // Elite menyimpan 365 hari: pesan yang sama harus bertahan.
        $this->assertSame(1, $elite->messages()->count());
    }

    /**
     * Catatan keuangan tidak pernah disentuh. `usage_counters` punya alasan
     * tambahan: ia satu-satunya sumber `Workspace::freeMessagesUsed()`, jadi
     * memangkasnya mengubah paket coba gratis jadi gratis selamanya.
     */
    public function test_data_bisnis_tidak_pernah_disentuh(): void
    {
        DB::table('usage_counters')->insert([
            'workspace_id' => $this->workspace->id,
            'period' => '2020-01',
            'messages_sent' => 5,
            'created_at' => now()->subYears(5),
            'updated_at' => now()->subYears(5),
        ]);

        (new Pemangkas)->jalankan();

        $this->assertSame(1, DB::table('usage_counters')->count());
    }

    /**
     * Bukti bahwa penghapusan benar-benar bertahap, bukan satu DELETE besar.
     *
     * Potongan dikecilkan jadi 10 supaya jumlah kueri bisa dihitung: 25 baris
     * harus menghasilkan beberapa putaran, bukan satu.
     */
    public function test_penghapusan_berjalan_bertahap(): void
    {
        config(['gateway.pemangkasan.potongan' => 10, 'gateway.pemangkasan.jeda_ms' => 0]);

        $this->kirimanWebhook(25);

        $hapus = 0;
        DB::listen(function ($kueri) use (&$hapus): void {
            if (str_starts_with(strtolower(trim($kueri->sql)), 'delete from "webhook_deliveries"')) {
                $hapus++;
            }
        });

        (new Pemangkas)->jalankan(hanya: 'webhook_deliveries');

        $this->assertSame(0, WebhookDelivery::count());
        $this->assertGreaterThan(
            1,
            $hapus,
            'Seluruh 25 baris terhapus dalam satu DELETE — persis yang menahan kunci MySQL bersama aplikasi pesanvo.'
        );
    }

    public function test_batas_total_per_jalan_dihormati(): void
    {
        config([
            'gateway.pemangkasan.potongan' => 5,
            'gateway.pemangkasan.jeda_ms' => 0,
            'gateway.pemangkasan.batas_per_tabel' => 10,
        ]);

        $this->kirimanWebhook(25);

        $hasil = (new Pemangkas)->jalankan(hanya: 'webhook_deliveries');

        $this->assertSame(10, $hasil['webhook_deliveries']);
        $this->assertSame(15, WebhookDelivery::count(), 'Sisanya harus diambil jalan berikutnya, bukan sekarang.');
    }

    public function test_perintah_kering_melaporkan_tanpa_menghapus(): void
    {
        $this->kirimanWebhook(4);

        $this->artisan('pesanvo:pangkas', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(4, WebhookDelivery::count());
    }

    public function test_perintah_bisa_dibatasi_ke_satu_tabel(): void
    {
        $this->kirimanWebhook(2);

        $lama = AuditLog::create(['action' => 'lama']);
        $this->tuakan('audit_logs', $lama->id, now()->subDays(400));

        $this->artisan('pesanvo:pangkas', ['--tabel' => 'webhook_deliveries'])
            ->assertSuccessful();

        $this->assertSame(0, WebhookDelivery::count());
        $this->assertSame(1, AuditLog::count(), 'Tabel di luar --tabel tidak boleh ikut tersentuh.');
    }

    public function test_perintah_menolak_tabel_yang_tidak_dikelola(): void
    {
        $this->artisan('pesanvo:pangkas', ['--tabel' => 'invoices'])
            ->assertFailed();
    }
}
