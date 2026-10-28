<?php

namespace Tests\Feature;

use App\Models\MessageTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Support\TemplateBawaan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Template bawaan: tersedia untuk semua workspace tanpa dibuat lebih dulu.
 *
 * Yang dijaga di sini terutama dua hal yang mudah rusak tanpa terasa:
 * placeholder-nya harus benar-benar bisa dibaca `MessageTemplate::render()`
 * (kalau sintaksnya meleset, isian tidak pernah terganti dan pelanggan
 * mengirim pesan berisi `{{ nama }}` ke pelanggannya sendiri), dan menyalinnya
 * harus menghasilkan salinan yang berdiri sendiri.
 */
class TemplateBawaanTest extends TestCase
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
        ]);

        $this->workspace->members()->attach($this->pemilik->id, ['role' => 'owner']);
        $this->berlangganan($this->workspace, 'prime');
    }

    public function test_setiap_template_bawaan_lengkap_dan_placeholdernya_terbaca(): void
    {
        $semua = TemplateBawaan::semua();

        $this->assertNotEmpty($semua);

        $slug = [];

        foreach ($semua as $t) {
            foreach (['slug', 'name', 'kategori', 'body'] as $wajib) {
                $this->assertArrayHasKey($wajib, $t);
                $this->assertNotEmpty($t[$wajib], "Template {$t['slug']} kehilangan {$wajib}.");
            }

            $this->assertNotContains($t['slug'], $slug, "Slug {$t['slug']} dipakai dua kali.");
            $slug[] = $t['slug'];

            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $t['slug']);
            $this->assertLessThanOrEqual(4096, mb_strlen($t['body']));

            /*
             | Yang paling penting: seluruh placeholder harus habis terganti
             | oleh parser yang sama dengan yang dipakai template buatan
             | pelanggan. Kalau sintaksnya meleset satu spasi pun, isian tidak
             | pernah terganti — dan yang mengirim pesan berisi "{{ nama }}"
             | ke pelanggannya sendiri adalah pelanggan kami.
            */
            $terisi = (new MessageTemplate(['body' => $t['body']]))
                ->render(array_fill_keys($t['variables'], 'X'));

            $this->assertStringNotContainsString(
                '{{',
                $terisi,
                "Ada placeholder di template {$t['slug']} yang tidak terbaca render()."
            );

            if ($t['variables'] !== []) {
                $this->assertStringContainsString('X', $terisi);
            }
        }
    }

    /** Tidak ada template pemasaran — itu keputusan sadar, bukan kelalaian. */
    public function test_tidak_ada_template_promosi(): void
    {
        foreach (TemplateBawaan::semua() as $t) {
            foreach (['diskon', 'promo', 'flash sale', 'buruan'] as $kata) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $kata,
                    $t['body'],
                    "Template {$t['slug']} berbau promosi. Mengirim promosi ke nomor yang tidak memintanya "
                    .'adalah cara tercepat nomor pelanggan diblokir WhatsApp.'
                );
            }
        }
    }

    public function test_halaman_kirim_pesan_menawarkan_template_bawaan(): void
    {
        $this->workspace->sessions()->create([
            'name' => 'CS', 'status' => 'connected', 'phone_number' => '6281111111111', 'connected_at' => now(),
        ]);

        $isi = $this->actingAs($this->pemilik)->get(route('messages.compose'))->assertOk()->getContent();

        // Workspace ini belum punya satu pun template sendiri, dan halamannya
        // tetap harus menawarkan sesuatu — halaman kosong adalah tempat orang
        // berhenti mencoba.
        $this->assertSame(0, $this->workspace->templates()->count());
        $this->assertStringContainsString('Kode verifikasi (OTP)', $isi);
        $this->assertStringContainsString('Bawaan · Pesanan', $isi);
    }

    public function test_menyalin_template_bawaan_menghasilkan_salinan_yang_berdiri_sendiri(): void
    {
        $this->actingAs($this->pemilik)
            ->post(route('templates.copy', 'pesanan-dikirim'))
            ->assertRedirect();

        $salinan = $this->workspace->templates()->firstOrFail();
        $bawaan = TemplateBawaan::cari('pesanan-dikirim');

        $this->assertSame($bawaan['name'], $salinan->name);
        $this->assertSame($bawaan['body'], $salinan->body);
        $this->assertSame($bawaan['variables'], $salinan->variables);

        // Diubah pelanggan — dan bawaannya tidak ikut berubah.
        $salinan->update(['body' => 'Versi saya sendiri']);

        $this->assertSame($bawaan['body'], TemplateBawaan::cari('pesanan-dikirim')['body']);
    }

    /** Menyalin dua kali tidak boleh gagal dengan galat database. */
    public function test_menyalin_dua_kali_menghasilkan_slug_berbeda(): void
    {
        foreach ([1, 2] as $_) {
            $this->actingAs($this->pemilik)->post(route('templates.copy', 'kode-otp'))->assertRedirect();
        }

        $this->assertSame(2, $this->workspace->templates()->count());
        $this->assertSame(['kode-otp', 'kode-otp-2'], $this->workspace->templates()->orderBy('slug')->pluck('slug')->all());
    }

    public function test_slug_bawaan_yang_tidak_ada_ditolak_dengan_rapi(): void
    {
        $this->actingAs($this->pemilik)
            ->post(route('templates.copy', 'tidak-pernah-ada'))
            ->assertSessionHasErrors('template');

        $this->assertSame(0, $this->workspace->templates()->count());
    }
}
