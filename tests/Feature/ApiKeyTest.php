<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * API key bisa dibuka lagi setelah dibuat.
 *
 * Sebelumnya kunci hanya tampil sekali seumur hidup. Yang terjadi di
 * pemakaian nyata bukan orang menjadi lebih hati-hati, melainkan kuncinya
 * disalin ke catatan pribadi, chat, atau screenshot — tempat yang jauh lebih
 * mudah bocor daripada database. Kunci sekarang disimpan terenkripsi dan hanya
 * bisa dibuka oleh owner atau admin workspace-nya.
 */
class ApiKeyTest extends TestCase
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
            'name' => 'Toko Uji',
            'slug' => 'toko-uji',
            'owner_id' => $this->owner->id,
            'owner_email' => $this->owner->email,
            'max_sessions' => 3,
            'monthly_message_quota' => 1000,
            'api_rate_limit_per_minute' => 60,
        ]);

        $this->workspace->members()->attach($this->owner->id, ['role' => 'owner']);

        // Workspace baru lahir `unpaid`; tes ini menguji hal lain,
        // jadi penagihannya tidak boleh ikut menghalangi.
        $this->berlangganan($this->workspace);

        $this->actingAs($this->owner);
        $this->withSession(['current_workspace_id' => $this->workspace->id]);
    }

    public function test_kunci_masih_bisa_dilihat_setelah_halaman_dimuat_ulang(): void
    {
        $this->post(route('api-keys.store'), ['name' => 'Aplikasi Kasir', 'scopes' => ['*']]);

        $plain = ApiKey::first()->plainKey();

        $this->assertNotNull($plain, 'Nilai kunci seharusnya tersimpan terenkripsi.');

        // Kunjungan terpisah, tanpa flash session dari pembuatan tadi.
        $this->get(route('api-keys.index'))
            ->assertOk()
            ->assertSee($plain);
    }

    /**
     * Yang tersimpan di kolom terenkripsi tidak boleh menggantikan hash.
     * Verifikasi permintaan API tetap harus lewat jalur yang tidak bisa dibalik.
     */
    public function test_hash_kunci_tetap_disimpan_terpisah(): void
    {
        $this->post(route('api-keys.store'), ['name' => 'Aplikasi Kasir']);

        $key = ApiKey::first();

        $this->assertNotSame($key->plainKey(), $key->key_hash);

        // Lewat kontrak modelnya, bukan lewat Hash::check langsung: sejak
        // rahasia API di-hash SHA-256 (alasannya di ApiKey::hashSecret), kelas
        // inilah yang tahu bentuk hash mana yang berlaku.
        $this->assertTrue($key->verifySecret(explode('.', $key->plainKey())[1]));
        $this->assertNotSame($key->plainKey(), $key->key_hash_fast);
        $this->assertFalse($key->verifySecret('bukan-rahasianya'));
    }

    /**
     * Baris database mentah tidak boleh memuat kunci polos — salinan database
     * saja seharusnya tidak cukup untuk memakai kunci siapa pun.
     */
    public function test_kunci_tidak_tersimpan_polos_di_database(): void
    {
        $this->post(route('api-keys.store'), ['name' => 'Aplikasi Kasir']);

        $key = ApiKey::first();
        $mentah = \DB::table('api_keys')->where('id', $key->id)->value('key_ciphertext');

        $this->assertNotSame($key->plainKey(), $mentah);
        $this->assertStringNotContainsString($key->plainKey(), (string) $mentah);
    }

    public function test_anggota_biasa_tidak_melihat_nilai_kunci(): void
    {
        $this->post(route('api-keys.store'), ['name' => 'Aplikasi Kasir']);
        $plain = ApiKey::first()->plainKey();

        $anggota = User::create([
            'name' => 'Anggota',
            'email' => 'anggota@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->workspace->members()->attach($anggota->id, ['role' => 'member']);

        $this->actingAs($anggota)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->get(route('api-keys.index'))
            ->assertOk()
            ->assertSee('Aplikasi Kasir')
            ->assertDontSee($plain);
    }

    public function test_anggota_biasa_tidak_bisa_mencabut_kunci(): void
    {
        $this->post(route('api-keys.store'), ['name' => 'Aplikasi Kasir']);
        $key = ApiKey::first();

        $anggota = User::create([
            'name' => 'Anggota',
            'email' => 'anggota@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->workspace->members()->attach($anggota->id, ['role' => 'member']);

        $this->actingAs($anggota)
            ->withSession(['current_workspace_id' => $this->workspace->id])
            ->delete(route('api-keys.destroy', $key->id))
            ->assertForbidden();

        $this->assertNull($key->fresh()->revoked_at);
    }

    /**
     * Kunci yang dicabut tidak pernah berguna lagi, jadi menyimpan nilainya
     * hanya menambah hal yang bisa bocor tanpa menambah kegunaan apa pun.
     */
    public function test_nilai_kunci_dibuang_saat_dicabut(): void
    {
        $this->post(route('api-keys.store'), ['name' => 'Aplikasi Kasir']);
        $key = ApiKey::first();
        $plain = $key->plainKey();

        $this->delete(route('api-keys.destroy', $key->id))->assertRedirect();

        $this->assertNotNull($key->fresh()->revoked_at);
        $this->assertNull($key->fresh()->plainKey());

        $this->get(route('api-keys.index'))->assertOk()->assertDontSee($plain);
    }

    /**
     * Kunci workspace A tidak boleh muncul saat workspace B yang sedang dibuka.
     */
    public function test_kunci_workspace_lain_tidak_ikut_tampil(): void
    {
        $this->post(route('api-keys.store'), ['name' => 'Kunci Toko Uji']);
        $plain = ApiKey::first()->plainKey();

        $lain = Workspace::create([
            'name' => 'Workspace Kedua',
            'slug' => 'workspace-kedua',
            'owner_id' => $this->owner->id,
            'owner_email' => $this->owner->email,
            'max_sessions' => 1,
        ]);

        $lain->members()->attach($this->owner->id, ['role' => 'owner']);

        $this->withSession(['current_workspace_id' => $lain->id])
            ->get(route('api-keys.index'))
            ->assertOk()
            ->assertDontSee($plain)
            ->assertSee('Belum ada API key di workspace ini');
    }
}
