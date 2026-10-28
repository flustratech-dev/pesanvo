<?php

namespace Tests\Feature;

use App\Jobs\RekamStatusJob;
use App\Models\StatusHarian;
use App\Models\StatusIncident;
use App\Models\User;
use App\Support\StatusLayanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('status:komponen');

        // Engine dianggap sehat kecuali sebuah tes menyatakan sebaliknya.
        Http::preventStrayRequests();
        Http::fake(['*/health' => Http::response(['ok' => true])]);
    }

    /**
     * Mengganti seluruh stub HTTP, bukan menambahinya.
     *
     * `Http::fake()` yang dipanggil dua kali MENGGABUNGKAN stubnya, dan pola
     * catch-all dari `setUp` tetap menang atas yang lebih spesifik di dalam
     * tes. Akibatnya tes yang bermaksud mematikan engine tetap mendapat engine
     * sehat — dan lulus untuk alasan yang salah, atau gagal tanpa sebab yang
     * kelihatan.
     */
    private function ganti(array $stub): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake($stub);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin-status@pesanvo.id',
            'password' => 'rahasia123',
            'is_super_admin' => true,
        ]);
    }

    /**
     * Halaman status terbuka TANPA login.
     *
     * Ini syarat utamanya, bukan detail: yang paling butuh halaman ini adalah
     * orang yang sedang tidak bisa masuk. Halaman status di balik login adalah
     * halaman status yang mati persis saat ia diperlukan.
     */
    public function test_halaman_status_terbuka_tanpa_login(): void
    {
        $this->get('/status')
            ->assertOk()
            ->assertSee('Semua layanan berjalan normal')
            ->assertSee('REST API')
            ->assertSee('Koneksi WhatsApp');
    }

    public function test_status_json_terbuka_tanpa_login(): void
    {
        $this->getJson('/status.json')
            ->assertOk()
            ->assertJsonPath('status', StatusLayanan::OPERASIONAL)
            ->assertJsonPath('komponen.api.keadaan', StatusLayanan::OPERASIONAL)
            ->assertJsonStructure(['status', 'komponen', 'insiden_berjalan', 'diperiksa_pada']);
    }

    /** Engine yang tidak menjawab harus benar-benar mengubah halamannya. */
    public function test_engine_mati_terbaca_di_halaman_status(): void
    {
        $this->ganti(['*/health' => Http::response('', 503)]);
        Cache::forget('status:komponen');

        $this->get('/status')
            ->assertOk()
            ->assertSee('Ada gangguan yang sedang berlangsung');

        $this->getJson('/status.json')->assertJsonPath('status', StatusLayanan::MATI);
    }

    /**
     * Pesan galat engine TIDAK boleh sampai ke halaman publik.
     *
     * Isinya alamat dan port internal — informasi yang tidak bisa
     * ditindaklanjuti pembacanya dan membocorkan susunan jaringan kami ke layar
     * yang bisa di-screenshot siapa saja.
     */
    public function test_alamat_internal_tidak_bocor_ke_halaman_publik(): void
    {
        $this->ganti(['*' => fn () => throw new \RuntimeException('cURL error 7: Failed to connect to 127.0.0.1 port 3100')]);
        Cache::forget('status:komponen');

        $this->get('/status')
            ->assertOk()
            ->assertDontSee('127.0.0.1')
            ->assertDontSee('3100')
            ->assertSee('Engine tidak dapat dihubungi');
    }

    public function test_insiden_berjalan_tampil_di_halaman_publik(): void
    {
        $insiden = StatusIncident::create([
            'title' => 'Pengiriman pesan tertunda',
            'summary' => 'Pesan tetap tersimpan dan akan terkirim setelah pulih.',
            'status' => 'menyelidiki',
            'kind' => 'gangguan',
            'impact' => 'sebagian',
            'started_at' => now()->subHour(),
        ]);

        $insiden->updates()->create([
            'status' => 'teridentifikasi',
            'body' => 'Penyebabnya sudah ditemukan.',
        ]);

        $this->get('/status')
            ->assertOk()
            ->assertSee('Pengiriman pesan tertunda')
            ->assertSee('Penyebabnya sudah ditemukan.');
    }

    /**
     * Menandai selesai HARUS mengisi `resolved_at`, bukan hanya `status`.
     *
     * Keduanya dibaca di tempat berbeda: label dari `status`, pemilahan
     * berjalan/lampau dari `resolved_at`. Mengisi salah satunya saja
     * menghasilkan insiden yang tertulis "Selesai" tapi tetap berkedip sebagai
     * gangguan aktif di puncak halaman publik, berhari-hari.
     */
    public function test_menandai_selesai_memindahkan_insiden_ke_riwayat(): void
    {
        $insiden = StatusIncident::create([
            'title' => 'Antrean macet',
            'summary' => 'Pekerja antrean berhenti bergerak.',
            'status' => 'menyelidiki',
            'kind' => 'gangguan',
            'impact' => 'total',
            'started_at' => now()->subHours(2),
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.status.update', $insiden->id), [
                'status' => 'selesai',
                'body' => 'Sudah pulih.',
            ])
            ->assertRedirect();

        $insiden->refresh();

        $this->assertNotNull($insiden->resolved_at, 'resolved_at wajib terisi saat status jadi selesai.');
        $this->assertSame(0, StatusIncident::berjalan()->count());

        $this->get('/status')
            ->assertOk()
            ->assertSee('Semua layanan berjalan normal')
            ->assertSee('Antrean macet');
    }

    /**
     * Panel admin merender kartu insiden berjalan beserta kotak kabarnya.
     *
     * Cabang ini tidak pernah tersentuh saat tabelnya kosong — dan itu justru
     * cabang yang dipakai orang di tengah gangguan, saat tidak ada waktu
     * menemukan halaman yang rusak.
     */
    public function test_panel_admin_menampilkan_insiden_berjalan(): void
    {
        StatusIncident::create([
            'title' => 'Webhook tertunda',
            'summary' => 'Penerusan pesan masuk melambat.',
            'status' => 'memantau',
            'kind' => 'gangguan',
            'impact' => 'sebagian',
            'started_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.status'))
            ->assertOk()
            ->assertSee('Webhook tertunda')
            ->assertSee('Kirim kabar')
            ->assertSee('Umumkan insiden baru');
    }

    /**
     * Selama HEARTBEAT_URL kosong, panel menyatakannya sebagai spanduk.
     *
     * Kalau keadaan itu cuma jadi ubin abu-abu, ia bisa berlangsung berbulan
     * tanpa disadari — dan selama itu satu kelas gangguan, yang paling parah,
     * tidak akan pernah dikabari kepada siapa pun.
     */
    public function test_panel_memperingatkan_saat_denyut_belum_dipasang(): void
    {
        config(['monitoring.heartbeat.url' => null]);

        $this->actingAs($this->admin())
            ->get(route('admin.status'))
            ->assertOk()
            ->assertSee('Tidak ada yang mengabari Anda kalau sistem ini mati total.')
            ->assertSee('HEARTBEAT_URL');
    }

    public function test_hanya_admin_yang_bisa_mengumumkan_insiden(): void
    {
        $biasa = User::create([
            'name' => 'Pelanggan',
            'email' => 'pelanggan-status@pesanvo.id',
            'password' => 'rahasia123',
        ]);

        $this->actingAs($biasa)
            ->post(route('admin.status.store'), [
                'title' => 'Palsu',
                'summary' => 'Tidak boleh masuk.',
                'kind' => 'gangguan',
                'impact' => 'total',
            ])
            ->assertNotFound();

        $this->assertSame(0, StatusIncident::count());
    }

    /** Sampel disimpan sebagai hitungan per hari, satu baris per komponen. */
    public function test_perekam_status_menambah_hitungan_harian(): void
    {
        app()->call([new RekamStatusJob, 'handle']);
        app()->call([new RekamStatusJob, 'handle']);

        $api = StatusHarian::where('component', 'api')->where('day', now()->toDateString())->first();

        $this->assertNotNull($api);
        $this->assertSame(2, $api->ok_count);
        $this->assertSame(0, $api->fail_count);
        $this->assertSame(count(StatusLayanan::daftarKomponen()), StatusHarian::count());
    }

    /**
     * Denyut TIDAK dikirim saat ada komponen yang mati.
     *
     * Ini yang membuat denyutnya berguna. Kalau ia dikirim tanpa syarat, yang
     * dibuktikannya cuma "proses PHP masih jalan" — tetap benar saat engine
     * mati dan tidak satu pun pesan pelanggan bisa keluar. Pemantau di ujung
     * sana akan menyalakan lampu hijau selama gangguan yang paling parah.
     */
    public function test_denyut_berhenti_saat_komponen_inti_mati(): void
    {
        config(['monitoring.heartbeat.url' => 'https://pemantau.contoh/denyut/abc']);

        $this->ganti([
            'pemantau.contoh/*' => Http::response('ok'),
            '*/health' => Http::response('', 503),
        ]);

        app()->call([new RekamStatusJob, 'handle']);

        Http::assertNotSent(fn ($req) => str_contains($req->url(), 'pemantau.contoh'));
    }

    public function test_denyut_terkirim_saat_semuanya_sehat(): void
    {
        config(['monitoring.heartbeat.url' => 'https://pemantau.contoh/denyut/abc']);

        $this->ganti([
            'pemantau.contoh/*' => Http::response('ok'),
            '*/health' => Http::response(['ok' => true]),
        ]);

        app()->call([new RekamStatusJob, 'handle']);

        Http::assertSent(fn ($req) => str_contains($req->url(), 'pemantau.contoh'));
    }

    /** Pemantau yang tidak terjangkau bukan gangguan pada layanan kami. */
    public function test_pemantau_luar_yang_mati_tidak_menjatuhkan_perekaman(): void
    {
        config(['monitoring.heartbeat.url' => 'https://pemantau.contoh/denyut/abc']);

        $this->ganti([
            'pemantau.contoh/*' => fn () => throw new \RuntimeException('tidak terjangkau'),
            '*/health' => Http::response(['ok' => true]),
        ]);

        app()->call([new RekamStatusJob, 'handle']);

        $this->assertSame(count(StatusLayanan::daftarKomponen()), StatusHarian::count());
    }

    /**
     * Hari tanpa sampel bernilai null, BUKAN 0 dan bukan 100.
     *
     * Keduanya berbohong ke arah berbeda: 0 menggambar gangguan seharian yang
     * tidak pernah terjadi, 100 menjanjikan ketersediaan yang tidak pernah
     * diukur. Sistem yang baru dipasang punya 89 hari seperti itu.
     */
    public function test_hari_tanpa_sampel_tidak_dihitung_sebagai_gangguan(): void
    {
        $baris = StatusHarian::create([
            'component' => 'api',
            'day' => now()->toDateString(),
            'ok_count' => 0,
            'fail_count' => 0,
        ]);

        $this->assertNull($baris->persen());

        $this->get('/status')->assertOk()->assertSee('belum diukur');
    }
}
