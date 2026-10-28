<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SessionService;
use App\Support\EngineError;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

/**
 * Galat engine tidak boleh sampai ke layar pelanggan dalam bentuk mentahnya.
 *
 * Yang pernah tampil di modal, apa adanya:
 *
 *   "Gagal menghubungi engine: cURL error 7: Failed to connect to 127.0.0.1
 *    port 3100 after 2034 ms ... for http://127.0.0.1:3100/sessions/01m1t.../start"
 *
 * Ia membocorkan alamat dan port internal beserta ULID sesi ke layar yang bisa
 * di-screenshot siapa saja, tidak bisa ditindaklanjuti oleh pembacanya, dan
 * terbaca seperti kerusakan permanen — padahal engine yang sedang di-deploy
 * ulang tidak memutus satu pun nomor yang sudah tertaut.
 */
class EngineErrorTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

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
        $this->berlangganan($this->workspace, 'prime');
    }

    public function test_engine_tidak_terjangkau_menghasilkan_kalimat_yang_bisa_dibaca(): void
    {
        $pesan = EngineError::pesan(new ConnectionException(
            'cURL error 7: Failed to connect to 127.0.0.1 port 3100 after 2034 ms: '
            .'Couldn\'t connect to server (see https://curl.se/libcurl/c/libcurl-errors.html) '
            .'for http://127.0.0.1:3100/sessions/01m1ttj3m6n8pstm4b593srq5r/start'
        ));

        // Yang paling ditanyakan orang saat layar ini muncul: nomor saya hilang?
        $this->assertStringContainsString('tidak terputus', $pesan);

        foreach (['cURL', '127.0.0.1', '3100', 'curl.se', '01m1ttj3m6n8pstm4b593srq5r'] as $bocor) {
            $this->assertStringNotContainsString($bocor, $pesan);
        }
    }

    public function test_batas_kapasitas_tidak_menyebut_nama_variabel_env(): void
    {
        $pesan = EngineError::pesan(new RuntimeException(
            'Engine sudah menjalankan 3 sesi (batas WA_MAX_SESSIONS).'
        ));

        $this->assertStringNotContainsString('WA_MAX_SESSIONS', $pesan);
        $this->assertStringContainsString('penuh', $pesan);
    }

    public function test_pesan_yang_memang_untuk_manusia_dibiarkan_apa_adanya(): void
    {
        $asli = 'Nomor tujuan tidak terdaftar di WhatsApp.';

        $this->assertSame($asli, EngineError::pesan(new RuntimeException($asli)));
    }

    /**
     * Dan lewat jalur sungguhan: tombol Hubungkan saat engine mati.
     */
    public function test_tombol_hubungkan_tidak_menampilkan_galat_mentah(): void
    {
        $session = $this->workspace->sessions()->create([
            'name' => 'CS',
            'status' => 'pending',
        ]);

        Http::fake(fn () => throw new ConnectionException(
            'cURL error 7: Failed to connect to 127.0.0.1 port 3100 after 2034 ms'
        ));

        $response = $this->actingAs($this->owner)
            ->post(route('sessions.connect', $session->id));

        $galat = $response->baseResponse->getSession()->get('errors')->first('session');

        $this->assertStringNotContainsString('cURL', $galat);
        $this->assertStringNotContainsString('127.0.0.1', $galat);

        // Dan yang tersimpan di baris sesi juga bersih — kolom itu ikut tampil
        // di kartu sesi dan di modal QR, jadi ia sama-sama muka pengguna.
        $this->assertStringNotContainsString('cURL', (string) $session->fresh()->last_error);

        // Sesinya tetap `disconnected`, bukan `failed`: engine yang tidak
        // terjangkau adalah keadaan sementara, dan `failed` mengeluarkan sesi
        // dari pemulihan otomatis saat engine hidup lagi.
        $this->assertSame('disconnected', $session->fresh()->status);
    }

    /**
     * TIDAK ADA tombol sesi yang boleh menghasilkan 500 saat engine mati.
     *
     * `connect` sudah dijaga sejak awal, tapi `disconnect` dan `logout` sama
     * sekali tidak punya penanganan — menekan "Putus tautan" saat engine tidak
     * menjawab melempar `ConnectionException` mentah dan pelanggan mendarat di
     * halaman Internal Server Error lengkap dengan jejak tumpukan, nama berkas,
     * dan potongan source code kami. Tes ini menyapu semuanya sekaligus supaya
     * tombol baru tidak lolos dengan lubang yang sama.
     */
    public function test_tidak_ada_tombol_sesi_yang_500_saat_engine_mati(): void
    {
        Http::fake(fn () => throw new ConnectionException(
            'cURL error 28: Operation timed out after 15002 milliseconds with 0 bytes received '
            .'for http://127.0.0.1:3100/sessions/01m1ttj3m6n8pstm4b593srq5r/logout'
        ));

        foreach (['connect', 'disconnect', 'logout'] as $aksi) {
            $session = $this->workspace->sessions()->create([
                'name' => 'CS '.$aksi,
                'status' => 'connected',
                'phone_number' => '6281111111111',
                'connected_at' => now(),
            ]);

            $response = $this->actingAs($this->owner)
                ->post(route("sessions.{$aksi}", $session->id));

            $this->assertNotSame(500, $response->status(), "Tombol {$aksi} menghasilkan 500 saat engine mati.");
            $response->assertRedirect();
        }

        // Dan Hapus tetap berhasil walau engine mati — kalau tidak, sesi hantu
        // yang tidak bisa diapa-apakan menumpuk di dashboard pelanggan.
        $session = $this->workspace->sessions()->create(['name' => 'CS hapus', 'status' => 'connected']);

        $this->actingAs($this->owner)
            ->delete(route('sessions.destroy', $session->id))
            ->assertRedirect(route('sessions.index'));

        // `fresh()` tidak dipakai: WaSession memakai hapus lunak, jadi barisnya
        // memang masih ada — yang penting ia hilang dari daftar pelanggan.
        $this->assertSoftDeleted($session);
        $this->assertNotContains(
            $session->id,
            $this->workspace->sessions()->pluck('id')->all(),
            'Sesi yang dihapus masih muncul di daftar workspace.'
        );
    }

    /**
     * Putus tautan yang gagal TIDAK boleh berpura-pura berhasil.
     *
     * Kegagalan tersering di sini adalah habis waktu 15 detik — bukan bukti
     * perintahnya tidak sampai. Menandai nomornya terputus padahal engine masih
     * memegangnya menghasilkan dashboard yang bilang "belum tertaut" sementara
     * nomor aslinya masih menerima pesan.
     */
    public function test_putus_tautan_yang_gagal_tidak_mengubah_keadaan_nomor(): void
    {
        $session = $this->workspace->sessions()->create([
            'name' => 'CS',
            'status' => 'connected',
            'phone_number' => '6281111111111',
            'connected_at' => now(),
        ]);

        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->actingAs($this->owner)->post(route('sessions.logout', $session->id));

        $session->refresh();

        $this->assertSame('connected', $session->status);
        $this->assertSame('6281111111111', $session->phone_number);
        $this->assertNotNull($session->connected_at);
    }

    /**
     * Sebaliknya, Hentikan memang boleh menandai berhenti: engine yang tidak
     * menjawab berarti ia tidak sedang menjalankan sesi ini juga, dan tombol
     * yang tidak pernah bisa berhasil lebih buruk daripada baris yang jujur.
     */
    public function test_hentikan_tetap_menandai_berhenti_saat_engine_mati(): void
    {
        $session = $this->workspace->sessions()->create([
            'name' => 'CS',
            'status' => 'connected',
            'phone_number' => '6281111111111',
            'connected_at' => now(),
        ]);

        Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect'));

        $this->actingAs($this->owner)->post(route('sessions.disconnect', $session->id));

        $this->assertSame('disconnected', $session->fresh()->status);

        // Nomornya tidak ikut dilepas — Hentikan bukan Putus tautan.
        $this->assertSame('6281111111111', $session->fresh()->phone_number);
    }

    public function test_galat_json_encode_menghasilkan_kalimat_sopan_dengan_kode_rujukan(): void
    {
        $rawError = 'json_encode error: Malformed UTF-8 characters, possibly incorrectly encoded';
        $exception = new RuntimeException($rawError);

        $pesan = EngineError::pesan($exception);

        // Teks teknis programmer tidak boleh lolos
        $this->assertStringNotContainsString('json_encode', $pesan);
        $this->assertStringNotContainsString('Malformed UTF-8', $pesan);

        // Menjelaskan apa yang terjadi pada nomor
        $this->assertStringContainsString('Nomor yang sudah tertaut tidak terpengaruh', $pesan);
        // Menjelaskan apa yang bisa dilakukan pengguna
        $this->assertStringContainsString('Coba lagi beberapa saat lagi', $pesan);
        // Menjelaskan kapan menghubungi bantuan
        $this->assertStringContainsString('Kalau berulang, hubungi bantuan', $pesan);

        // Kode rujukan ringkas ada di dalam pesan
        $kode = EngineError::kodeRujukan($exception);
        $this->assertMatchesRegularExpression('/^REF-[A-Z0-9]{6}$/', $kode);
        $this->assertStringContainsString("(kode: {$kode})", $pesan);

        // Pemanggilan berulang pada instance exception yang sama menghasilkan kode yang konsisten
        $this->assertSame($pesan, EngineError::pesan($exception));
    }

    public function test_galat_teknis_tak_terduga_dicatat_ke_log_dengan_kode_rujukan(): void
    {
        Log::spy();

        $exception = new RuntimeException('Fatal error in vendor/guzzlehttp/src/Client.php:123');
        $pesan = EngineError::pesan($exception);
        $kode = EngineError::kodeRujukan($exception);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context) use ($kode) {
                return str_contains($message, $kode)
                    && ($context['rujukan'] ?? null) === $kode
                    && str_contains($context['message'] ?? '', 'Fatal error');
            });

        $this->assertStringNotContainsString('Fatal error', $pesan);
        $this->assertStringNotContainsString('vendor/guzzlehttp', $pesan);
        $this->assertStringContainsString($kode, $pesan);
    }

    public function test_tombol_hubungkan_tidak_membocorkan_json_encode_error_ke_pengguna_maupun_database(): void
    {
        $session = $this->workspace->sessions()->create([
            'name' => 'CS',
            'status' => 'pending',
        ]);

        Http::fake(fn () => throw new RuntimeException(
            'json_encode error: Malformed UTF-8 characters, possibly incorrectly encoded'
        ));

        $response = $this->actingAs($this->owner)
            ->post(route('sessions.connect', $session->id));

        $galat = $response->baseResponse->getSession()->get('errors')->first('session');

        // Di session flash / modal: bersih dari galat mentah
        $this->assertStringNotContainsString('json_encode', $galat);
        $this->assertStringNotContainsString('Malformed UTF-8', $galat);
        $this->assertMatchesRegularExpression('/kode: REF-[A-Z0-9]{6}/', $galat);

        // Di database last_error: juga bersih dan memuat kode rujukan yang sama
        $lastError = (string) $session->fresh()->last_error;
        $this->assertStringNotContainsString('json_encode', $lastError);
        $this->assertMatchesRegularExpression('/kode: REF-[A-Z0-9]{6}/', $lastError);
        $this->assertSame($galat, $lastError);
    }

    public function test_jalur_api_connect_tidak_membocorkan_galat_teknis(): void
    {
        [, $key] = ApiKey::issue($this->workspace, 'kunci api');

        $session = $this->workspace->sessions()->create([
            'name' => 'CS API',
            'status' => 'pending',
        ]);

        Http::fake(fn () => throw new RuntimeException(
            'Call to undefined method GuzzleHttp\\Client::send()'
        ));

        $response = $this->withHeader('X-Api-Key', $key)
            ->postJson("/api/v1/sessions/{$session->id}/connect");

        $response->assertStatus(502);
        $errorMessage = $response->json('error.message');

        $this->assertStringNotContainsString('Call to undefined method', $errorMessage);
        $this->assertStringNotContainsString('GuzzleHttp', $errorMessage);
        $this->assertMatchesRegularExpression('/kode: REF-[A-Z0-9]{6}/', $errorMessage);
    }

    public function test_jalur_kirim_pesan_tidak_membocorkan_galat_teknis_ke_database_maupun_ui(): void
    {
        [, $key] = ApiKey::issue($this->workspace, 'kunci api');

        $session = $this->workspace->sessions()->create([
            'name' => 'CS Pesan',
            'status' => 'connected',
            'phone_number' => '6281111111111',
        ]);

        Http::fake(fn () => throw new RuntimeException(
            'json_encode error: Malformed UTF-8 characters, possibly incorrectly encoded'
        ));

        $response = $this->withHeader('X-Api-Key', $key)
            ->postJson('/api/v1/messages/text', [
                'session_id' => $session->id,
                'to' => '081234567890',
                'message' => 'Uji pesan',
            ]);

        // Pada QUEUE_CONNECTION=sync, eksekusi job langsung berjalan saat antre.
        // Baik pada respons API maupun baris pesan di database, galat teknis tidak boleh bocor.
        $errorMessage = $response->json('error.message');
        $this->assertStringNotContainsString('json_encode', (string) $errorMessage);
        $this->assertStringNotContainsString('Malformed UTF-8', (string) $errorMessage);
        $this->assertMatchesRegularExpression('/kode: REF-[A-Z0-9]{6}/', (string) $errorMessage);

        $message = Message::first();
        $this->assertNotNull($message);
        $this->assertStringNotContainsString('json_encode', (string) $message->error);
        $this->assertStringNotContainsString('Malformed UTF-8', (string) $message->error);
        $this->assertMatchesRegularExpression('/kode: REF-[A-Z0-9]{6}/', (string) $message->error);
    }

    public function test_jalur_store_sesi_tidak_membocorkan_galat_tak_terduga(): void
    {
        $mock = $this->mock(SessionService::class);
        $mock->shouldReceive('create')->andThrow(new RuntimeException('SQLSTATE[HY000]: General error in file.php:99'));

        $response = $this->actingAs($this->owner)
            ->post(route('sessions.store'), ['name' => 'Sesi Uji']);

        $galat = $response->baseResponse->getSession()->get('errors')->first('name');
        $this->assertStringNotContainsString('SQLSTATE', (string) $galat);
        $this->assertStringNotContainsString('file.php', (string) $galat);
        $this->assertMatchesRegularExpression('/kode: REF-[A-Z0-9]{6}/', (string) $galat);
    }
}
