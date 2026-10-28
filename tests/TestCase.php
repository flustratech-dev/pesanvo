<?php

namespace Tests;

use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Totp;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * Masuk sebagai seorang pengguna yang faktor keduanya sudah terlewati.
     *
     * `EnsureTwoFactor` berlaku di SELURUH halaman web, jadi tanpa ini setiap
     * tes yang membuka halaman apa pun akan dijawab pengalihan ke `/2fa` — dan
     * puluhan tes akan diperbaiki dengan cara yang salah, yaitu dengan
     * melonggarkan penegakannya di produksi.
     *
     * Yang dikerjakan di sini persis dua hal yang di produksi dikerjakan
     * pengguna sungguhan: menandai tantangan sudah dilewati, dan — untuk akun
     * yang wajib memakainya — memasang 2FA lebih dulu. Tidak ada penjagaan yang
     * dimatikan.
     *
     * `TwoFactorTest` sengaja TIDAK lewat sini: ia memakai `be()`, yang tidak
     * ditimpa, supaya yang diujinya benar-benar penegakan apa adanya.
     */
    public function actingAs(Authenticatable $user, $guard = null): static
    {
        parent::actingAs($user, $guard);

        if ($user instanceof User && $user->wajibDuaFaktor() && ! $user->duaFaktorAktif()) {
            $user->forceFill([
                'two_factor_secret' => Totp::rahasiaBaru(),
                'two_factor_recovery_codes' => [],
                'two_factor_confirmed_at' => now(),
            ])->save();
        }

        $this->withSession(['2fa.lolos' => true]);

        return $this;
    }

    /**
     * Memberi workspace langganan yang berlaku.
     *
     * Workspace baru lahir di paket coba gratis: hidup, tapi hanya lima pesan
     * seumur hidupnya dan dengan batas paling kecil. Itu memang yang diinginkan
     * di produksi, tapi tes yang sedang menguji hal lain (API key, pengaturan
     * workspace, batas paket) tidak boleh ikut terbentur jatah itu.
     *
     * Yang menguji penagihannya sendiri sengaja TIDAK memakai helper ini; di
     * sana keadaan langganannya justru yang sedang diperiksa.
     */
    /**
     * Isi `<main>` sebuah halaman, tanpa bilah atas dan sidebar.
     *
     * Ada sejak lonceng notifikasi dipasang di bilah atas SETIAP halaman.
     * `assertDontSee` atas seluruh dokumen berhenti berarti "tidak ada di
     * daftar" — ia ikut membaca judul notifikasi, yang memang menyebut nomor
     * tagihan dan judul tiket. Tesnya yang terlalu luas, bukan loncengnya yang
     * salah: yang ingin dijaga selalu isi halamannya.
     */
    protected function isiUtama(TestResponse $response): string
    {
        $html = $response->getContent();

        if (! preg_match('#<main[^>]*>(.*)</main>#s', $html, $cocok)) {
            return $html;
        }

        return $cocok[1];
    }

    protected function berlangganan(Workspace $workspace, string $plan = 'prime'): Subscription
    {
        $workspace->subscription()?->delete();

        $subscription = $workspace->subscription()->create([
            'plan_slug' => $plan,
            'period' => 'monthly',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        // Cerminnya di baris workspace — di situlah MessageDispatcher dan
        // AuthenticateApiKey membaca boleh-tidaknya sebuah workspace dipakai.
        // `plan_slug` ikut, kalau tidak `isFreeTier()` masih menjawab benar dan
        // jatah lima pesan tetap berlaku untuk workspace yang sudah membayar.
        // Batas paket sengaja TIDAK ikut ditimpa: tiap tes menyetel angkanya
        // sendiri, dan menimpanya di sini membuat tes batas menguji paket,
        // bukan yang sedang mereka periksa.
        $workspace->forceFill([
            'plan_slug' => $plan,
            'status' => 'active',
            'service_until' => $subscription->current_period_end,
        ])->save();
        $workspace->refresh();

        return $subscription;
    }
}
