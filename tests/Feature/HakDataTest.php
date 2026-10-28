<?php

namespace Tests\Feature;

use App\Jobs\BersihkanEksporJob;
use App\Jobs\EksekusiPenghapusanAkunJob;
use App\Jobs\SusunEksporDataJob;
use App\Models\DataExport;
use App\Models\Invoice;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

/**
 * Ekspor data dan penghapusan akun mandiri — hak menurut UU PDP.
 */
class HakDataTest extends TestCase
{
    use RefreshDatabase;

    private User $pemilik;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->pemilik = User::create([
            'name' => 'Owner',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Toko Contoh',
            'slug' => 'toko-contoh',
            'owner_id' => $this->pemilik->id,
            'owner_email' => $this->pemilik->email,
            'max_sessions' => 1,
            'monthly_message_quota' => 3000,
        ]);

        $this->workspace->members()->attach($this->pemilik->id, ['role' => 'owner']);

        $this->berlangganan($this->workspace);
    }

    private function masuk(): static
    {
        return $this->actingAs($this->pemilik)
            ->withSession(['current_workspace_id' => $this->workspace->id]);
    }

    // ---------------------------------------------------------------- ekspor

    /**
     * Halaman Pengaturan benar-benar merender bagian ekspornya.
     *
     * Tombol yang rutenya ada tapi tidak pernah tergambar adalah fitur yang
     * hanya ada di daftar rute — dan tidak ada satu pun tes yang menyadarinya
     * selama yang diperiksa cuma POST-nya.
     */
    public function test_halaman_pengaturan_menampilkan_bagian_ekspor(): void
    {
        $this->masuk()
            ->get(route('settings'))
            ->assertOk()
            ->assertSee('Ekspor data workspace')
            ->assertSee('Minta ekspor data');
    }

    public function test_halaman_profil_menampilkan_dampak_penghapusan(): void
    {
        $this->masuk()
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Hapus akun')
            ->assertSee('Yang akan terjadi:')
            // Nama workspace yang ikut terhapus disebut satu per satu.
            ->assertSee('Toko Contoh');
    }

    public function test_pelanggan_bisa_meminta_ekspor_data(): void
    {
        $this->masuk()->post(route('settings.export'))->assertRedirect();

        $this->assertSame(1, DataExport::where('workspace_id', $this->workspace->id)->count());
    }

    /**
     * Satu permintaan per 24 jam.
     *
     * Bukan penghematan biaya: tiap ekspor menyusun berkas yang bisa ratusan
     * megabyte dan menahan satu pekerja antrean selama itu — pekerja yang sama
     * yang mengirim pesan seluruh pelanggan.
     */
    public function test_permintaan_kedua_dalam_sehari_ditolak(): void
    {
        $this->masuk()->post(route('settings.export'));
        $this->masuk()->post(route('settings.export'));

        $this->assertSame(1, DataExport::count());
    }

    public function test_berkas_ekspor_berisi_pesan_dan_tanpa_kunci_api(): void
    {
        Message::create([
            'workspace_id' => $this->workspace->id,
            'direction' => 'outbound',
            'to_number' => '628111222333',
            'type' => 'text',
            'body' => 'Halo, pesanan Anda sudah dikirim ✅',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $ekspor = DataExport::create([
            'workspace_id' => $this->workspace->id,
            'requested_by' => $this->pemilik->id,
            'status' => 'menunggu',
        ]);

        app()->call([new SusunEksporDataJob($ekspor->id), 'handle']);

        $ekspor->refresh();

        $this->assertSame('siap', $ekspor->status, 'Ekspor gagal: '.$ekspor->error);
        $this->assertTrue($ekspor->bisaDiunduh());

        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($ekspor->path));

        $csv = $zip->getFromName('pesan.csv');
        $kunci = $zip->getFromName('api-key.json');

        $this->assertStringContainsString('628111222333', $csv);
        $this->assertStringContainsString('pesanan Anda sudah dikirim', $csv);
        $this->assertStringContainsString('BACA_SAYA.txt', implode(',', array_map(
            fn ($i) => $zip->getNameIndex($i),
            range(0, $zip->numFiles - 1)
        )));

        // Berkas ini berpindah lewat email dan chat. Kunci di dalamnya adalah
        // kunci yang bocor tanpa pemiliknya pernah tahu dari mana.
        $this->assertStringNotContainsString('key_hash', (string) $kunci);
        $this->assertStringNotContainsString('key_ciphertext', (string) $kunci);

        $zip->close();
    }

    public function test_ekspor_workspace_lain_tidak_bisa_diunduh(): void
    {
        $orangLain = User::create([
            'name' => 'Orang Lain',
            'email' => 'lain@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $wsLain = Workspace::create([
            'name' => 'Punya Orang',
            'slug' => 'punya-orang',
            'owner_id' => $orangLain->id,
            'owner_email' => $orangLain->email,
            'max_sessions' => 1,
            'monthly_message_quota' => 100,
        ]);

        $milikOrangLain = DataExport::create([
            'workspace_id' => $wsLain->id,
            'requested_by' => $orangLain->id,
            'status' => 'siap',
            'path' => 'ekspor/x/rahasia.zip',
            'expires_at' => now()->addDay(),
        ]);

        $this->masuk()
            ->get(route('settings.export.download', $milikOrangLain->id))
            ->assertNotFound();
    }

    public function test_berkas_kedaluwarsa_tidak_bisa_diunduh_lagi(): void
    {
        $ekspor = DataExport::create([
            'workspace_id' => $this->workspace->id,
            'requested_by' => $this->pemilik->id,
            'status' => 'siap',
            'path' => 'ekspor/x/lama.zip',
            'expires_at' => now()->subDay(),
        ]);

        $this->masuk()
            ->get(route('settings.export.download', $ekspor->id))
            ->assertStatus(410);
    }

    /**
     * Berkasnya dibuang, barisnya tidak.
     *
     * Tiap berkas memuat seluruh isi percakapan sebuah workspace; membiarkannya
     * menumpuk adalah kebocoran yang menunggu terjadi. Tapi catatan bahwa
     * ekspornya pernah diminta harus tetap ada — itu yang membuktikan hak
     * aksesnya kami penuhi kalau suatu saat dipersoalkan.
     */
    public function test_berkas_kedaluwarsa_dibuang_tapi_catatannya_tinggal(): void
    {
        Storage::disk('local')->put('ekspor/x/lama.zip', 'isi');

        $ekspor = DataExport::create([
            'workspace_id' => $this->workspace->id,
            'requested_by' => $this->pemilik->id,
            'status' => 'siap',
            'path' => 'ekspor/x/lama.zip',
            'expires_at' => now()->subDay(),
        ]);

        app()->call([new BersihkanEksporJob, 'handle']);

        $this->assertFalse(Storage::disk('local')->exists('ekspor/x/lama.zip'));
        $this->assertSame('kedaluwarsa', $ekspor->refresh()->status);
        $this->assertNull($ekspor->path);
    }

    // -------------------------------------------------------- hapus akun

    public function test_penghapusan_dijadwalkan_bukan_langsung(): void
    {
        $this->masuk()
            ->post(route('profile.delete.request'), ['password' => 'rahasia12345'])
            ->assertRedirect();

        $this->pemilik->refresh();

        $this->assertNotNull($this->pemilik->deletion_scheduled_for);
        $this->assertTrue($this->pemilik->deletion_scheduled_for->isFuture());

        // Yang paling penting: belum ada yang hilang.
        $this->assertDatabaseHas('users', ['id' => $this->pemilik->id]);
        $this->assertDatabaseHas('workspaces', ['id' => $this->workspace->id]);
    }

    public function test_kata_sandi_salah_membatalkan_penghapusan(): void
    {
        $this->masuk()
            ->post(route('profile.delete.request'), ['password' => 'bukan-ini'])
            ->assertSessionHasErrors('password');

        $this->assertNull($this->pemilik->refresh()->deletion_scheduled_for);
    }

    public function test_penghapusan_bisa_dibatalkan_sebelum_jatuh_tempo(): void
    {
        $this->masuk()->post(route('profile.delete.request'), ['password' => 'rahasia12345']);
        $this->masuk()->post(route('profile.delete.cancel'))->assertRedirect();

        $this->assertNull($this->pemilik->refresh()->deletion_scheduled_for);
    }

    public function test_akun_yang_belum_jatuh_tempo_tidak_ikut_dihapus(): void
    {
        $this->pemilik->forceFill([
            'deletion_requested_at' => now(),
            'deletion_scheduled_for' => now()->addDays(3),
        ])->save();

        app()->call([new EksekusiPenghapusanAkunJob, 'handle']);

        $this->assertDatabaseHas('users', ['id' => $this->pemilik->id]);
    }

    /**
     * Tagihan TIDAK ikut terhapus bersama workspace-nya.
     *
     * Dokumen pembukuan wajib disimpan sepuluh tahun menurut ketentuan
     * perpajakan, dan Kebijakan Privasi kami menjanjikannya tetap disimpan meski
     * pelanggan meminta penghapusan. Sebelum penjagaan ini ada, `forceDelete()`
     * pada workspace ikut membuangnya lewat `cascadeOnDelete` — tanpa galat,
     * tanpa gejala, dan baru ketahuan saat ada yang mencarinya.
     */
    public function test_tagihan_tetap_tersimpan_setelah_akun_dihapus(): void
    {
        $tagihan = Invoice::create([
            'number' => 'INV-UJI-001',
            'external_id' => (string) Str::ulid(),
            'workspace_id' => $this->workspace->id,
            'plan_slug' => 'prime',
            'period' => 'monthly',
            'amount' => 249_000,
            'tax_amount' => 0,
            'unique_code' => 123,
            'total' => 249_123,
            'status' => 'paid',
            'due_at' => now(),
            'paid_at' => now(),
            'paid_by_user_id' => $this->pemilik->id,
        ]);

        $this->pemilik->forceFill([
            'deletion_requested_at' => now()->subDays(20),
            'deletion_scheduled_for' => now()->subDay(),
        ])->save();

        app()->call([new EksekusiPenghapusanAkunJob, 'handle']);

        $this->assertDatabaseMissing('users', ['id' => $this->pemilik->id]);
        $this->assertDatabaseMissing('workspaces', ['id' => $this->workspace->id]);

        $tagihan->refresh();

        $this->assertSame('INV-UJI-001', $tagihan->number);
        $this->assertSame(249_123, (int) $tagihan->total);
        $this->assertNull($tagihan->workspace_id, 'Tagihan harus lepas dari workspace, bukan ikut terhapus.');
        $this->assertNull($tagihan->paid_by_user_id, 'Kaitan ke orangnya harus diputus.');
    }

    /**
     * Workspace yang masih punya anggota lain BERPINDAH pemilik, tidak dihapus.
     *
     * Riwayat percakapan di dalamnya milik mereka juga. Pengguna yang keluar
     * dari sebuah tim tidak boleh membawa serta data orang yang tidak meminta
     * apa pun.
     */
    public function test_workspace_beranggota_lain_berpindah_pemilik(): void
    {
        $rekan = User::create([
            'name' => 'Rekan',
            'email' => 'rekan@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->workspace->members()->attach($rekan->id, ['role' => 'member']);

        $this->pemilik->forceFill([
            'deletion_requested_at' => now()->subDays(20),
            'deletion_scheduled_for' => now()->subDay(),
        ])->save();

        app()->call([new EksekusiPenghapusanAkunJob, 'handle']);

        $this->assertDatabaseMissing('users', ['id' => $this->pemilik->id]);

        $this->workspace->refresh();

        $this->assertSame($rekan->id, $this->workspace->owner_id);
        $this->assertSame($rekan->email, $this->workspace->owner_email);
        $this->assertSame(1, $this->workspace->members()->count());
    }

    public function test_admin_tidak_bisa_menghapus_akunnya_sendiri(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-hakdata@pesanvo.id',
            'password' => Hash::make('rahasia12345'),
            'is_super_admin' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('profile.delete.request'), ['password' => 'rahasia12345'])
            ->assertForbidden();

        $this->assertNull($admin->refresh()->deletion_scheduled_for);
    }
}
