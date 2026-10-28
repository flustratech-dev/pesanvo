<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Verifikasi API key: biayanya, kompatibilitasnya, dan batasnya.
 *
 * Rahasia API key adalah `Str::random(40)` — sekitar 238 bit acak, bukan kata
 * sandi pilihan manusia. bcrypt di sana lambat tanpa membeli apa pun, dan
 * lambatnya terukur: 276 ms CPU per pemeriksaan, dibayar pada SETIAP permintaan
 * API termasuk yang berhasil. Satu pelanggan Elite memakai batas yang kita jual
 * sendiri (300 permintaan/menit) menghabiskan 83 detik CPU per menit di VPS
 * 2 vCPU yang juga menjalankan MySQL dan dua puluh container lain.
 */
class ApiKeyHashTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): Workspace
    {
        return Workspace::create([
            'name' => 'Contoh',
            'slug' => 'contoh',
            'max_sessions' => 2,
            'monthly_message_quota' => 100,
            'api_rate_limit_per_minute' => 60,
        ]);
    }

    public function test_kunci_baru_punya_hash_cepat_dan_tetap_punya_bcrypt(): void
    {
        [$key] = ApiKey::issue($this->workspace(), 'kunci uji');

        $this->assertSame(64, strlen((string) $key->key_hash_fast), 'SHA-256 heksa selalu 64 karakter.');

        // bcrypt tetap ada, dan itu yang membuat rollback cukup mengembalikan
        // kode: kode lama membaca `key_hash` seperti biasa.
        $this->assertStringStartsWith('$2', (string) $key->key_hash);
    }

    /**
     * Syarat penerimaan perpindahan ini: rollback tidak boleh menuntut satu
     * langkah pun di database. Diuji dengan meniru persis yang dilakukan kode
     * lama — `Hash::check()` atas kolom `key_hash`.
     */
    public function test_kode_lama_masih_bisa_memverifikasi_kunci_baru(): void
    {
        [, $plain] = ApiKey::issue($this->workspace(), 'kunci uji');

        [$prefix, $rahasia] = explode('.', $plain, 2);

        $key = ApiKey::where('prefix', $prefix)->first();

        $this->assertTrue(
            Hash::check($rahasia, $key->key_hash),
            'Kode lama tidak akan bisa memverifikasi kunci ini setelah rollback.'
        );
    }

    public function test_verifikasi_tidak_lagi_membayar_bcrypt_setelah_hash_cepat_ada(): void
    {
        [$key, $plain] = ApiKey::issue($this->workspace(), 'kunci uji');

        [, $rahasia] = explode('.', $plain, 2);

        $mulai = microtime(true);

        for ($i = 0; $i < 20; $i++) {
            $key->verifySecret($rahasia);
        }

        $rata = (microtime(true) - $mulai) * 1000 / 20;

        // bcrypt cost 12 terukur 276 ms per pemeriksaan di mesin pengembangan.
        // Batas 20 ms longgar sekali; yang dijaga bukan kecepatannya melainkan
        // bahwa jalur bcrypt sudah TIDAK dilewati sama sekali.
        $this->assertLessThan(
            20,
            $rata,
            "Verifikasi memakan {$rata} ms — jalur bcrypt masih dilewati."
        );
    }

    /**
     * Kunci yang sudah ada di produksi ber-hash bcrypt. Tidak satu pun boleh
     * berhenti bekerja: pelanggan yang API key-nya tiba-tiba ditolak adalah
     * integrasi yang mati tanpa satu pun peringatan.
     */
    public function test_kunci_bcrypt_lama_tetap_diterima(): void
    {
        $workspace = $this->workspace();
        $secret = Str::random(40);
        $prefix = 'fwa_lamalama';

        $key = ApiKey::create([
            'workspace_id' => $workspace->id,
            'name' => 'Kunci warisan',
            'prefix' => $prefix,
            'key_hash' => Hash::make($secret),
            'scopes' => ['*'],
        ]);

        $this->assertStringStartsWith('$2', $key->key_hash);

        $this->withHeader('X-Api-Key', $prefix.'.'.$secret)
            ->getJson('/api/v1/health')
            ->assertOk();
    }

    public function test_kunci_bcrypt_lama_naik_ke_bentuk_cepat_setelah_sekali_dipakai(): void
    {
        $workspace = $this->workspace();
        $secret = Str::random(40);

        $key = ApiKey::create([
            'workspace_id' => $workspace->id,
            'name' => 'Kunci warisan',
            'prefix' => 'fwa_lamalama',
            'key_hash' => Hash::make($secret),
            'scopes' => ['*'],
        ]);

        $key->verifySecret($secret);

        $this->assertSame(
            64,
            strlen((string) $key->fresh()->key_hash_fast),
            'Kunci yang terbukti benar harus mendapat hash cepat, kalau tidak ia membayar 276 ms selamanya.'
        );

        // `key_hash` TIDAK berubah — itu jaring pengaman rollback.
        $this->assertStringStartsWith('$2', (string) $key->fresh()->key_hash);

        // Dan tetap bekerja sesudah dinaikkan.
        $this->assertTrue($key->fresh()->verifySecret($secret));
    }

    public function test_rahasia_salah_ditolak_pada_kedua_bentuk_hash(): void
    {
        $workspace = $this->workspace();

        [$baru] = ApiKey::issue($workspace, 'baru');
        $this->assertFalse($baru->verifySecret(Str::random(40)));

        $lama = ApiKey::create([
            'workspace_id' => $workspace->id,
            'name' => 'lama',
            'prefix' => 'fwa_lamalama',
            'key_hash' => Hash::make(Str::random(40)),
            'scopes' => ['*'],
        ]);
        $this->assertFalse($lama->verifySecret(Str::random(40)));
        $this->assertNull($lama->fresh()->key_hash_fast, 'Rahasia salah tidak boleh menaikkan hash.');
    }

    /**
     * Setelah `pesanvo:hash-api-bersihkan`, `key_hash` sendiri berisi SHA-256
     * dan `key_hash_fast` kosong. Verifikasi harus tetap bekerja — dan sejak
     * saat itu rollback memang tidak lagi mungkin tanpa langkah tambahan.
     */
    public function test_keadaan_setelah_pembersihan_tetap_bisa_diverifikasi(): void
    {
        $workspace = $this->workspace();
        [$key, $plain] = ApiKey::issue($workspace, 'kunci uji');

        [, $rahasia] = explode('.', $plain, 2);

        $this->artisan('pesanvo:hash-api-bersihkan')->assertSuccessful();

        $key->refresh();

        $this->assertNull($key->key_hash_fast);
        $this->assertStringStartsNotWith('$2', (string) $key->key_hash);
        $this->assertTrue($key->verifySecret($rahasia));
        $this->assertFalse($key->verifySecret(Str::random(40)));

        // Dan permintaan API sungguhan tetap diterima.
        $this->withHeader('X-Api-Key', $plain)->getJson('/api/v1/health')->assertOk();
    }

    public function test_pembersihan_kering_tidak_mengubah_apa_pun(): void
    {
        [$key] = ApiKey::issue($this->workspace(), 'kunci uji');

        $this->artisan('pesanvo:hash-api-bersihkan', ['--dry-run' => true])->assertSuccessful();

        $this->assertStringStartsWith('$2', (string) $key->fresh()->key_hash, 'Mode kering mengubah sesuatu.');
        $this->assertNotNull($key->fresh()->key_hash_fast);
    }

    /**
     * F2: percobaan yang gagal dibatasi, dan dibatasi SEBELUM verifikasi.
     */
    public function test_percobaan_gagal_beruntun_dibatasi(): void
    {
        $workspace = $this->workspace();
        [$key] = ApiKey::issue($workspace, 'kunci uji');

        for ($i = 0; $i < 20; $i++) {
            $this->withHeader('X-Api-Key', $key->prefix.'.salah'.$i)
                ->getJson('/api/v1/health')
                ->assertStatus(401);
        }

        $this->withHeader('X-Api-Key', $key->prefix.'.salah-lagi')
            ->getJson('/api/v1/health')
            ->assertStatus(429);
    }

    public function test_kunci_yang_benar_tidak_pernah_menyentuh_ember_kegagalan(): void
    {
        $workspace = $this->workspace();
        [$key, $plain] = ApiKey::issue($workspace, 'kunci uji');

        for ($i = 0; $i < 30; $i++) {
            $this->withHeader('X-Api-Key', $plain)->getJson('/api/v1/health')->assertOk();
        }

        $this->assertSame(0, RateLimiter::attempts('apikey-gagal:'.$key->prefix));
    }

    /**
     * Prefix bukan bagian rahasia dari kunci, jadi ember disusun per prefix +
     * IP. Serangan terhadap satu pelanggan tidak boleh ikut mengunci pelanggan
     * lain yang kebetulan sekantor dan ber-IP sama.
     */
    public function test_kunci_pelanggan_lain_tidak_ikut_terkunci(): void
    {
        $workspace = $this->workspace();
        [$korban] = ApiKey::issue($workspace, 'korban');

        $lain = Workspace::create([
            'name' => 'Lain',
            'slug' => 'lain',
            'max_sessions' => 1,
            'monthly_message_quota' => 100,
            'api_rate_limit_per_minute' => 60,
        ]);
        [, $plainLain] = ApiKey::issue($lain, 'tetangga');

        for ($i = 0; $i < 25; $i++) {
            $this->withHeader('X-Api-Key', $korban->prefix.'.salah'.$i)->getJson('/api/v1/health');
        }

        $this->withHeader('X-Api-Key', $plainLain)
            ->getJson('/api/v1/health')
            ->assertOk();
    }
}
