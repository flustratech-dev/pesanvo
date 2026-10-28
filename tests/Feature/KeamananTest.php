<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Penjagaan header keamanan.
 *
 * Yang paling penting di berkas ini bukan "header ada", melainkan **HSTS tidak
 * pernah keluar di HTTP**. Header itu membuat browser menolak http:// untuk
 * host yang bersangkutan selama setahun, dan sekali tersimpan ia tidak bisa
 * dibatalkan dengan menghapus kodenya — pengembang yang terkena harus
 * membersihkannya dari setelan browsernya sendiri. Satu commit ceroboh di sini
 * membuat http://127.0.0.1:8051 tidak bisa dibuka siapa pun di tim.
 */
class KeamananTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_keamanan_terpasang_di_seluruh_respons(): void
    {
        $jawaban = $this->get('/');

        $jawaban->assertHeader('X-Frame-Options', 'DENY');
        $jawaban->assertHeader('X-Content-Type-Options', 'nosniff');
        $jawaban->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $jawaban->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_hsts_keluar_saat_https(): void
    {
        $this->get('https://localhost/')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_hsts_tidak_pernah_keluar_saat_http(): void
    {
        $this->get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_respons_api_ikut_mendapat_header(): void
    {
        // API dijawab JSON dan lewat grup middleware yang berbeda dari halaman
        // web; header yang cuma menempel di grup `web` melewatkan seluruh
        // permukaan yang justru paling sering dipanggil dari luar.
        $this->getJson('/api/v1/sessions')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_login_dibatasi_lima_percobaan_per_menit(): void
    {
        User::create([
            'name' => 'Orang',
            'email' => 'orang@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        // Percobaan keenam ditolak 429 walau kata sandinya kebetulan benar —
        // yang membatasi jumlah tebakan, bukan kebenaran tebakannya.
        foreach (range(1, 5) as $ke) {
            $this->post('/login', ['email' => 'orang@contoh.id', 'password' => 'salah'])
                ->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => 'orang@contoh.id', 'password' => 'salah'])
            ->assertStatus(429);
    }

    public function test_halaman_sistem_menyebut_empat_pemeriksaan_keamanan(): void
    {
        $admin = User::create([
            'name' => 'Pesanvo Finance',
            'email' => 'finance@pesanvo.id',
            'password' => Hash::make('rahasia12345'),
            'is_super_admin' => true,
        ]);

        $this->actingAs($admin)->get('/admin/sistem')
            ->assertOk()
            ->assertSee('Sambungan HTTPS')
            ->assertSee('Cookie sesi terkunci')
            ->assertSee('Header keamanan')
            ->assertSee('Mode debug');
    }
}
