<?php

namespace Tests\Feature;

use App\Support\DocsRepository;
use App\Support\Legal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Enam dokumen hukum di /docs.
 */
class HukumTest extends TestCase
{
    use RefreshDatabase;

    private const HALAMAN = [
        'syarat-layanan',
        'kebijakan-privasi',
        'dpa',
        'penggunaan-wajar',
        'sla',
        'kebijakan-refund',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    /**
     * Terbuka tanpa login, semuanya.
     *
     * Bagian hukum dan pengadaan calon pelanggan meminta tautan yang bisa
     * mereka buka sendiri — dan mereka belum punya akun. Dokumen hukum di balik
     * login adalah dokumen yang tidak pernah sampai ke orang yang memintanya.
     */
    public function test_seluruh_dokumen_hukum_terbuka_tanpa_login(): void
    {
        foreach (self::HALAMAN as $slug) {
            $this->get(route('docs.show', $slug))
                ->assertOk()
                ->assertSee(DocsRepository::flat()[$slug]['title']);
        }
    }

    /**
     * Identitas badan usaha disisipkan dari satu sumber, bukan ditulis
     * berulang di enam berkas.
     */
    public function test_identitas_badan_usaha_tersisip_ke_dokumennya(): void
    {
        config(['legal.entity.name' => 'PT UJI COBA MANDIRI']);
        Cache::flush();

        $this->get(route('docs.show', 'syarat-layanan'))
            ->assertOk()
            ->assertSee('PT UJI COBA MANDIRI');
    }

    /**
     * Yang belum diisi HARUS terlihat mencolok, bukan menghilang.
     *
     * Kalau penanda kosong dirender sebagai string kosong, yang tayang adalah
     * kalimat "diselenggarakan oleh , berkedudukan di ." — terbaca seperti
     * salah ketik biasa, bisa bertahan bertahun-tahun tanpa ada yang
     * melaporkannya, dan membuat seluruh dokumen tidak menyebut siapa yang
     * sebenarnya terikat.
     */
    public function test_identitas_yang_belum_diisi_tampil_mencolok(): void
    {
        config(['legal.entity.name' => null]);
        Cache::flush();

        $this->get(route('docs.show', 'syarat-layanan'))
            ->assertOk()
            ->assertSee('BELUM DIISI: nama badan usaha');
    }

    public function test_penanda_kosong_terdaftar_supaya_bisa_diperiksa_admin(): void
    {
        config(['legal.entity.name' => null, 'legal.entity.address' => 'Jalan Uji No. 1']);

        $kurang = Legal::belumLengkap();

        $this->assertArrayHasKey('legal.entity.name', $kurang);
        $this->assertArrayNotHasKey('legal.entity.address', $kurang);
    }

    /**
     * Risiko pemblokiran nomor oleh WhatsApp WAJIB dinyatakan sebelum membeli.
     *
     * Ini risiko nyata yang tidak bisa kami hilangkan dan tidak bisa kami
     * batalkan. Kalau tidak tertulis di muka, satu pemblokiran berubah dari
     * risiko yang pelanggan terima menjadi kesalahan yang kami tanggung.
     */
    public function test_syarat_layanan_menyatakan_risiko_pemblokiran_whatsapp(): void
    {
        $halaman = $this->get(route('docs.show', 'syarat-layanan'))->assertOk();

        $halaman->assertSee('bukan WhatsApp Business API resmi', false);
        $halaman->assertSee('dapat dibatasi atau diblokir oleh WhatsApp', false);
    }

    /**
     * Pengecualian retensi pembukuan harus disebut terbuka di Kebijakan
     * Privasi — dan kodenya benar-benar melakukannya (`HakDataTest`).
     */
    public function test_kebijakan_privasi_menyebut_pengecualian_data_tagihan(): void
    {
        $this->get(route('docs.show', 'kebijakan-privasi'))
            ->assertOk()
            ->assertSee('Kewajiban perpajakan', false)
            ->assertSee((string) config('legal.retention.billing_years'), false);
    }

    /** Yang dikecualikan dari SLA menentukan nilai sebenarnya dari janjinya. */
    public function test_sla_menyebut_pengecualiannya(): void
    {
        $this->get(route('docs.show', 'sla'))
            ->assertOk()
            ->assertSee('Yang TIDAK dihitung sebagai gangguan', false)
            ->assertSee('Pemeliharaan terjadwal', false);
    }

    public function test_dpa_menyebut_pembagian_peran_pengendali_dan_prosesor(): void
    {
        $this->get(route('docs.show', 'dpa'))
            ->assertOk()
            ->assertSee('Pengendali Data', false)
            ->assertSee('Prosesor Data', false);
    }

    /**
     * Keenamnya tertaut dari footer.
     *
     * Dokumen hukum yang hanya bisa ditemukan kalau alamatnya sudah diketahui
     * adalah dokumen yang tidak dianggap ada oleh bagian pengadaan yang
     * mencarinya.
     */
    public function test_keenam_dokumen_tertaut_dari_halaman_depan(): void
    {
        $halaman = $this->get('/')->assertOk();

        foreach (self::HALAMAN as $slug) {
            $halaman->assertSee(route('docs.show', $slug), false);
        }
    }

    /** Halaman status juga tertaut, karena SLA merujuknya. */
    public function test_halaman_status_tertaut_dari_halaman_depan(): void
    {
        $this->get('/')->assertOk()->assertSee(route('status'), false);
    }

    /**
     * Dokumen hukum tunduk pada batas yang sama dengan docs lain: tidak boleh
     * membocorkan cara memasang source code. `DocsTest` menjaganya secara
     * menyeluruh; di sini dipastikan halaman barunya ikut terjaring.
     */
    public function test_dokumen_hukum_tidak_membocorkan_source_code(): void
    {
        foreach (self::HALAMAN as $slug) {
            $this->get(route('docs.show', $slug))
                ->assertOk()
                ->assertDontSee('git clone')
                ->assertDontSee('github.com');
        }
    }
}
