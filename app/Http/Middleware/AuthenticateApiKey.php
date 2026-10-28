<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi REST API publik memakai kunci berformat `vwa_xxxxxxxx.<secret>`.
 *
 * Kunci yang terbit sebelum pergantian merek berawalan `fwa_` dan tetap sah:
 * kunci dicari lewat kolom `prefix` apa adanya, bukan dengan mencocokkan
 * awalannya. Jangan menambah pemeriksaan `str_starts_with($kunci, 'vwa_')` —
 * itu mematikan seluruh integrasi yang sudah berjalan tanpa satu pun dari
 * mereka mengubah apa pun.
 *
 * Kunci dikirim lewat header `X-Api-Key`, atau `Authorization: Bearer <kunci>`
 * untuk klien yang lebih nyaman dengan pola bearer.
 */
class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        $plain = $request->header('X-Api-Key') ?: $request->bearerToken();

        if (! $plain || ! str_contains($plain, '.')) {
            return $this->deny('API key tidak disertakan atau formatnya salah.', 401);
        }

        [$prefix, $secret] = explode('.', $plain, 2);

        /*
         | Percobaan yang GAGAL dibatasi, dan dibatasi SEBELUM verifikasi.
         |
         | Sebelum ini tidak ada batas sama sekali pada percobaan yang salah:
         | rate limiter di bawah baru berjalan setelah autentikasi berhasil.
         | Dua akibatnya, dan yang kedua lebih berbahaya:
         |
         | 1. Menebak rahasia kunci bisa dicoba tanpa henti. (Ruang tebakannya
         |    memang 238 bit, jadi ini bukan ancaman nyata — tapi batas yang
         |    tidak ada tetap batas yang tidak ada.)
         | 2. Tiap percobaan memaksa satu verifikasi hash. Selama hash-nya masih
         |    bcrypt warisan, satu percobaan = 276 ms CPU, dan beberapa
         |    permintaan bersamaan sudah cukup menyaturasi VPS 2 vCPU yang juga
         |    menjalankan MySQL dan dua puluh container lain.
         |
         | Ember disusun per prefix + IP, bukan per IP saja: prefix adalah bagian
         | kunci yang bukan rahasia, jadi memakainya membuat serangan terhadap
         | satu pelanggan tidak ikut mengunci pelanggan lain yang kebetulan
         | sekantor dan ber-IP sama.
         |
         | IP di sini TIDAK bisa dipercaya sepenuhnya — `trustProxies(at: '*')`
         | di bootstrap/app.php menerima X-Forwarded-For dari siapa pun, jadi
         | penyerang bisa memutar IP untuk menghindari ember ini. Karena itu ada
         | ember kedua per prefix saja, dengan batas jauh lebih longgar: ia tidak
         | bisa dihindari dengan memutar IP, dan longgar supaya pelanggan yang
         | sah tidak bisa dikunci orang lain hanya dengan menebak-nebak.
         | Keduanya hanya menghitung KEGAGALAN; permintaan yang benar tidak
         | pernah menyentuhnya.
        */
        $emberIp = 'apikey-gagal:'.$prefix.':'.$request->ip();
        $emberPrefix = 'apikey-gagal:'.$prefix;

        if (RateLimiter::tooManyAttempts($emberIp, 20)
            || RateLimiter::tooManyAttempts($emberPrefix, 200)) {
            return $this->deny(
                'Terlalu banyak percobaan API key yang gagal. Coba lagi dalam '
                    .max(RateLimiter::availableIn($emberIp), RateLimiter::availableIn($emberPrefix)).' detik.',
                429
            );
        }

        $key = ApiKey::with('workspace')->where('prefix', $prefix)->first();

        if (! $key || ! $key->verifySecret($secret)) {
            RateLimiter::hit($emberIp, 60);
            RateLimiter::hit($emberPrefix, 60);

            return $this->deny('API key tidak dikenali.', 401);
        }

        // Kunci yang benar membersihkan jejak kegagalannya sendiri: pelanggan
        // yang salah ketik lalu memperbaikinya tidak boleh tetap terhitung.
        RateLimiter::clear($emberIp);

        if (! $key->isUsable()) {
            return $this->deny('API key sudah dicabut atau kedaluwarsa.', 401);
        }

        if (! $key->workspace || ! $key->workspace->isActive()) {
            return $this->deny('Workspace pemilik API key sedang tidak aktif.', 403);
        }

        if ($scope && ! $key->allows($scope)) {
            return $this->deny("API key tidak punya scope '{$scope}'.", 403);
        }

        /*
         | Batas permintaan per menit adalah salah satu pembeda antar paket, dan
         | sampai sekarang kolomnya diisi tanpa pernah ditegakkan di mana pun.
         |
         | Dihitung per WORKSPACE, bukan per API key: menghitung per kunci
         | membuat batasnya bisa dilipatgandakan hanya dengan membuat kunci baru,
         | yang justru gratis. Nilai 0 berarti tanpa batas, mengikuti perjanjian
         | yang sama dengan kuota pesan.
        */
        $batas = (int) $key->workspace->api_rate_limit_per_minute;

        if ($batas > 0) {
            $ember = "api:{$key->workspace_id}";

            if (RateLimiter::tooManyAttempts($ember, $batas)) {
                return $this->deny(
                    "Batas {$batas} permintaan per menit terlampaui. Coba lagi dalam "
                        .RateLimiter::availableIn($ember).' detik.',
                    429
                );
            }

            RateLimiter::hit($ember, 60);
        }

        // Menulis last_used_at pada setiap request akan menjadi satu UPDATE per
        // pesan terkirim. Cukup dicatat sekali per menit — kolom ini hanya untuk
        // informasi "terakhir dipakai" di dashboard.
        $stamp = "apikey:{$key->id}:touched";

        if (! Cache::has($stamp)) {
            $key->forceFill([
                'last_used_at' => now(),
                'last_used_ip' => $request->ip(),
            ])->saveQuietly();

            Cache::put($stamp, true, now()->addMinute());
        }

        $request->attributes->set('api_key', $key);
        $request->attributes->set('workspace', $key->workspace);

        return $next($request);
    }

    private function deny(string $message, int $status): Response
    {
        return response()->json([
            'success' => false,
            'error' => ['message' => $message],
        ], $status);
    }
}
