<?php

namespace Tests\Feature;

use App\Jobs\DeliverWebhookJob;
use App\Models\ApiKey;
use App\Models\User;
use App\Models\Webhook;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as PermintaanKeluar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pergantian merek ke Pesanvo, dan hal-hal yang TIDAK boleh ikut rusak olehnya.
 *
 * Yang paling mahal kalau salah bukan teks yang terlewat, melainkan integrasi
 * yang sudah berjalan: API key lama yang tiba-tiba ditolak, atau admin yang
 * berlipat dua karena seeder tidak lagi mengenali admin lama.
 */
class PergantianMerekTest extends TestCase
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

    private function jalankanMigrasiPindahAdmin(): void
    {
        $migrasi = require database_path('migrations/2026_09_19_100000_pindahkan_admin_ke_identitas_pesanvo.php');
        $migrasi->up();
    }

    private function adminPesanvo(array $timpa = []): void
    {
        config(['pesanvo.admin' => array_merge([
            'name' => 'Administrator',
            'email' => 'admin@gmail.com',
            'password' => 'sandi-admin-baru',
            'phone' => '6285774410978',
            'company' => 'Pesanvo Cloud Indonesia',
        ], $timpa)]);
    }

    public function test_kunci_baru_berawalan_vwa(): void
    {
        [$key, $plain] = ApiKey::issue($this->workspace(), 'kunci uji');

        $this->assertStringStartsWith('vwa_', $key->prefix);
        $this->assertStringStartsWith('vwa_', $plain);
    }

    /**
     * Kunci yang terbit sebelum pergantian merek berawalan `fwa_` dan sudah
     * tertempel di `.env` aplikasi-aplikasi yang berintegrasi. Satu pun tidak
     * boleh berhenti bekerja hanya karena awalannya berbeda dari kunci baru.
     */
    public function test_kunci_berawalan_lama_tetap_diterima(): void
    {
        [$key, $plain] = ApiKey::issue($this->workspace(), 'kunci sebelum ganti merek');

        [, $rahasia] = explode('.', $plain, 2);
        $prefixLama = 'fwa_'.substr($key->prefix, 4);
        $key->forceFill(['prefix' => $prefixLama])->save();

        $this->withHeader('X-Api-Key', $prefixLama.'.'.$rahasia)
            ->getJson('/api/v1/health')
            ->assertOk();
    }

    public function test_webhook_keluar_ditandatangani_dengan_header_pesanvo(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $webhook = Webhook::create([
            'workspace_id' => $this->workspace()->id,
            'url' => 'https://contoh.test/webhook',
            'secret' => 'rahasia-webhook',
            'events' => ['message.received'],
            'is_active' => true,
        ]);

        (new DeliverWebhookJob($webhook->id, 'message.received', ['id' => 'x']))->handle();

        Http::assertSent(function (PermintaanKeluar $permintaan) {
            return $permintaan->hasHeader('X-Pesanvo-Event', 'message.received')
                && hash_equals(
                    hash_hmac('sha256', $permintaan->body(), 'rahasia-webhook'),
                    $permintaan->header('X-Pesanvo-Signature')[0] ?? ''
                );
        });
    }

    /**
     * Admin lama diubah di tempat: baris yang sama, jadi workspace, API key,
     * dan nomor tertaut miliknya tetap di tangan orang yang sama.
     */
    public function test_admin_lama_dipindahkan_ke_identitas_pesanvo_di_tempat(): void
    {
        $this->adminPesanvo();

        $lama = User::create([
            'name' => 'Admin Lama',
            'email' => 'admin-lama@contoh.id',
            'password' => Hash::make('sandi-lama'),
            'is_super_admin' => true,
            'email_verified_at' => now()->subYear(),
        ]);
        $workspace = $this->workspace();
        $workspace->forceFill(['owner_id' => $lama->id])->save();

        $this->jalankanMigrasiPindahAdmin();

        $admin = $lama->fresh();
        $this->assertSame('admin@gmail.com', $admin->email);
        $this->assertSame('Administrator', $admin->name);
        $this->assertSame('6285774410978', $admin->phone);
        $this->assertSame('Pesanvo Cloud Indonesia', $admin->company);
        $this->assertTrue((bool) $admin->is_super_admin);
        $this->assertTrue(Hash::check('sandi-admin-baru', $admin->password));

        $this->assertSame(1, User::where('is_super_admin', true)->count(), 'Tidak boleh lahir admin kedua.');
        $this->assertSame($lama->id, $workspace->fresh()->owner_id);

        $jejak = DB::table('audit_logs')->where('action', 'admin.identitas_dipindahkan')->first();
        $this->assertNotNull($jejak, 'Email lama harus tercatat, karena tidak disimpan di tempat lain.');
        $this->assertSame('admin-lama@contoh.id', json_decode($jejak->context, true)['email_lama']);
    }

    public function test_kata_sandi_tidak_diganti_kalau_env_kosong(): void
    {
        $this->adminPesanvo(['password' => null]);

        $lama = User::create([
            'name' => 'Admin Lama',
            'email' => 'admin-lama@contoh.id',
            'password' => Hash::make('sandi-lama'),
            'is_super_admin' => true,
        ]);

        $this->jalankanMigrasiPindahAdmin();

        $this->assertSame('admin@gmail.com', $lama->fresh()->email);
        $this->assertTrue(Hash::check('sandi-lama', $lama->fresh()->password));
    }

    public function test_tidak_berbuat_apa_pun_kalau_email_admin_baru_sudah_ada(): void
    {
        $this->adminPesanvo();

        $lama = User::create([
            'name' => 'Admin Lama',
            'email' => 'admin-lama@contoh.id',
            'password' => Hash::make('sandi-lama'),
            'is_super_admin' => true,
        ]);
        User::create([
            'name' => 'Sudah Ada',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('x'),
        ]);

        $this->jalankanMigrasiPindahAdmin();

        $this->assertSame('admin-lama@contoh.id', $lama->fresh()->email);
        $this->assertSame(0, DB::table('audit_logs')->where('action', 'admin.identitas_dipindahkan')->count());
    }

    public function test_hanya_super_admin_tertua_yang_dipindahkan(): void
    {
        $this->adminPesanvo();

        $pertama = User::create([
            'name' => 'Pertama',
            'email' => 'pertama@contoh.id',
            'password' => Hash::make('x'),
            'is_super_admin' => true,
        ]);
        $kedua = User::create([
            'name' => 'Kedua',
            'email' => 'kedua@contoh.id',
            'password' => Hash::make('x'),
            'is_super_admin' => true,
        ]);

        $this->jalankanMigrasiPindahAdmin();

        $this->assertSame('admin@gmail.com', $pertama->fresh()->email);
        $this->assertSame('kedua@contoh.id', $kedua->fresh()->email);
    }

    /**
     * Nomor bisnis dibaca dari satu tempat. Dulu nomornya tertulis mati di lima
     * Blade, dan pergantian nomor pertama meninggalkan separuhnya menunjuk
     * nomor lama.
     */
    public function test_tautan_whatsapp_publik_memakai_nomor_dari_config(): void
    {
        config(['billing.enterprise.whatsapp' => '085808749131']);

        $this->get('/')
            ->assertOk()
            ->assertSee('wa.me/6285808749131', false)
            ->assertDontSee('6282318280376', false);
    }

    /**
     * Satu wajah ikon untuk kedua aplikasi — maksud aslinya tetap berlaku:
     * berpindah antara pesanvo dan WA Gateway tidak boleh terasa seperti
     * membuka dua produk berbeda.
     *
     * Yang berubah hanya bentuknya. `pesanvo.png` berukuran 549x303, dan
     * Google Search mengabaikan favicon yang tidak persegi lalu menggantinya
     * dengan ikon bawaan di hasil pencarian — jadi ikon "yang sama" itu dulu
     * tidak pernah benar-benar tampil di sana. Sekarang keduanya memakai berkas
     * persegi turunan yang identik, bukan sekadar mirip.
     */
    public function test_favicon_sama_dengan_pesanvo(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<link rel="icon" href="'.asset('favicon.ico').'" sizes="48x48">', false)
            ->assertDontSee('images/pesanvo.png" sizes', false);

        // Logo lanskap tetap dipakai sebagai gambar pratinjau tautan dan logo
        // di header; yang tidak boleh lagi adalah memakainya sebagai favicon.
        $this->assertFileExists(public_path('images/pesanvo.png'));
    }

    public function test_database_kosong_dibiarkan_untuk_seeder(): void
    {
        $this->adminPesanvo();

        $this->jalankanMigrasiPindahAdmin();

        $this->assertSame(0, User::count());
    }

    public function test_copyright_footer_menampilkan_pesanvo_dan_pesanvo(): void
    {
        $expected = 'Pesanvo. All rights reserved. Created by pesanvo.';
        $lama = 'PT NAMA PERUSAHAAN. Hak cipta dilindungi undang-undang.';

        $this->get('/')
            ->assertOk()
            ->assertSee($expected, false)
            ->assertDontSee($lama, false);

        $this->get('/login')
            ->assertOk()
            ->assertSee($expected, false)
            ->assertDontSee($lama, false);

        $this->get('/register')
            ->assertOk()
            ->assertSee($expected, false)
            ->assertDontSee($lama, false);
    }
}
