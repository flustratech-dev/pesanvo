<?php

namespace Tests\Feature;

use App\Support\DocsRepository;
use App\Support\Plan;
use Tests\TestCase;

class DocsTest extends TestCase
{
    public function test_indeks_dokumentasi_bisa_diakses_publik(): void
    {
        $this->get('/docs')
            ->assertOk()
            ->assertSee('Dokumentasi')
            ->assertSee('Mulai Cepat');
    }

    /**
     * Setiap halaman di katalog harus benar-benar bisa dibuka. Katalog dan
     * berkas markdown-nya dua hal terpisah — berkas yang dihapus atau diganti
     * nama tanpa memperbarui katalog akan menghasilkan 404 yang baru ketahuan
     * saat ada yang mengklik tautannya.
     */
    public function test_semua_halaman_di_katalog_bisa_dibuka(): void
    {
        foreach (DocsRepository::flat() as $slug => $page) {
            $this->get("/docs/{$slug}")
                ->assertOk()
                ->assertSee($page['title']);
        }
    }

    /**
     * Dokumentasi publik ini untuk pengguna produk. Cara memasang source code,
     * alamat repositori, dan seluk-beluk internal sistem tidak boleh muncul di
     * sini — semuanya ada di folder docs/ yang tidak pernah disajikan lewat web.
     */
    public function test_dokumentasi_publik_tidak_membocorkan_source_code(): void
    {
        $terlarang = ['git clone', 'github.com', 'composer install', 'npm install', 'php artisan'];

        foreach (array_keys(DocsRepository::flat()) as $slug) {
            $html = $this->get("/docs/{$slug}")->getContent();

            foreach ($terlarang as $frasa) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $frasa,
                    $html,
                    "Halaman docs/{$slug} memuat '{$frasa}' — itu urusan internal, bukan untuk pengguna."
                );
            }
        }
    }

    public function test_halaman_utama_tidak_membocorkan_source_code(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringNotContainsString('git clone', $html);
        $this->assertStringNotContainsString('github.com', $html);
    }

    /**
     * Berkas di docs/ adalah dokumentasi teknis internal dan tidak boleh bisa
     * dibuka lewat web, meski namanya ditebak dengan benar.
     */
    public function test_dokumentasi_internal_tidak_bisa_dibuka_lewat_web(): void
    {
        foreach (['arsitektur', 'referensi-kode', 'referensi-database', 'engine', 'deployment', 'kontribusi'] as $slug) {
            $this->get("/docs/{$slug}")->assertNotFound();
        }
    }

    public function test_slug_yang_tidak_ada_di_katalog_menghasilkan_404(): void
    {
        $this->get('/docs/entah-apa')->assertNotFound();
    }

    /**
     * Slug tidak boleh bisa dipakai menyusun path berkas. Rute dibatasi daftar
     * putih, jadi upaya menembus folder ditolak sebelum menyentuh filesystem.
     */
    public function test_slug_tidak_bisa_dipakai_membaca_berkas_di_luar_docs(): void
    {
        $this->get('/docs/..%2F..%2F.env')->assertNotFound();
        $this->get('/docs/'.urlencode('../../.env'))->assertNotFound();
    }

    /**
     * Dokumen saling menaut memakai nama berkas (`WEBHOOK.md`), yang benar saat
     * berkasnya dibaca langsung tapi 404 di web. Tautan itu harus diterjemahkan
     * ke URL halaman, termasuk teksnya.
     */
    public function test_tautan_antar_dokumen_diterjemahkan_ke_url_halaman(): void
    {
        $html = $this->get('/docs/mulai-cepat')->getContent();

        $this->assertStringNotContainsString('href="WEBHOOK.md"', $html);
        $this->assertStringNotContainsString('>WEBHOOK.md<', $html);
        $this->assertStringContainsString(route('docs.show', 'webhook'), $html);
    }

    public function test_tautan_docs_muncul_di_header_halaman_utama(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(route('docs.index'));
    }

    /**
     * Tabel dipakai di hampir semua halaman referensi. Kalau renderer-nya
     * kehilangan dukungan tabel, isinya berubah jadi barisan teks berpipa
     * yang tidak terbaca.
     */
    public function test_tabel_markdown_dirender_sebagai_tabel(): void
    {
        $this->get('/docs/referensi-api')
            ->assertOk()
            ->assertSee('<table', false);
    }

    public function test_judul_punya_id_supaya_bisa_ditautkan(): void
    {
        $page = DocsRepository::page('mulai-cepat');

        $this->assertNotEmpty($page['toc']);
        $this->assertStringContainsString('id="'.$page['toc'][0]['id'].'"', $page['html']);
    }

    /**
     * Tabel paket di halaman publik harus sama dengan `config/plans.php`.
     *
     * Angkanya ditulis dua kali — sekali sebagai config yang ditegakkan, sekali
     * sebagai tabel Markdown yang dibaca calon pelanggan — dan itu memang
     * terpaksa: berkas dokumentasi tidak bisa memanggil config. Yang tidak
     * boleh terjadi adalah keduanya berbeda tanpa ada yang tahu. Halaman harga
     * yang menjanjikan angka berbeda dari yang ditegakkan adalah janji yang
     * kita langgar di hadapan orang yang baru saja membayar — dan ini sudah
     * sekali nyaris terjadi: kuota diturunkan di config, tabelnya tertinggal.
     */
    public function test_tabel_paket_di_docs_sama_dengan_config(): void
    {
        $isi = file_get_contents(resource_path('docs/LANGGANAN_DAN_TAGIHAN.md'));

        foreach (Plan::all() as $plan) {
            $batas = $plan->limits();

            $angka = [
                'harga bulanan' => 'Rp '.number_format($plan->price('monthly'), 0, ',', '.'),
                'harga tahunan' => 'Rp '.number_format($plan->price('yearly'), 0, ',', '.'),
                'kuota pesan' => number_format($batas['monthly_message_quota'], 0, ',', '.'),
                'batas API' => (string) $batas['api_rate_limit_per_minute'],
            ];

            foreach ($angka as $apa => $nilai) {
                $this->assertStringContainsString(
                    $nilai,
                    $isi,
                    "{$apa} paket {$plan->name()} ({$nilai}) tidak ada di tabel docs publik — "
                    .'config dan halaman yang dibaca pelanggan sudah berbeda.'
                );
            }
        }
    }
}
