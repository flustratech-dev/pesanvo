<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menjaga endpoint akun tertaut — satu-satunya pintu yang bisa membuat akun
 * dan mengganti kata sandi orang lain di aplikasi ini tanpa sesi.
 *
 * Pola yang sama dengan VerifyEngineSignature: timestamp menolak pemutaran
 * ulang, HMAC atas timestamp + isi body membuktikan asalnya aplikasi pesanvo.
 * Rahasia yang kosong mematikan endpoint sama sekali (503), bukan membukanya.
 */
class VerifyLinkedAccountSignature
{
    private const MAX_SKEW_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.linked_accounts.secret');

        if (blank($secret)) {
            return $this->tolak('Penautan akun belum dikonfigurasi.', 503);
        }

        $tandaTangan = (string) $request->header('X-Akun-Signature');
        $waktu = (string) $request->header('X-Akun-Timestamp');

        if ($tandaTangan === '' || $waktu === '' || ! ctype_digit($waktu)) {
            return $this->tolak('Tanda tangan tidak lengkap.', 401);
        }

        if (abs(now()->getTimestamp() - (int) $waktu) > self::MAX_SKEW_SECONDS) {
            return $this->tolak('Tanda tangan sudah kedaluwarsa.', 401);
        }

        if (! hash_equals(hash_hmac('sha256', $waktu.'.'.$request->getContent(), $secret), $tandaTangan)) {
            // Dicatat keras: tanda tangan salah berulang ke endpoint yang bisa
            // mengganti kata sandi adalah tanda seseorang sedang mencoba.
            Log::warning('Akun tertaut: tanda tangan ditolak.', ['ip' => $request->ip()]);

            return $this->tolak('Tanda tangan tidak cocok.', 401);
        }

        return $next($request);
    }

    private function tolak(string $pesan, int $status): Response
    {
        return response()->json([
            'success' => false,
            'error' => ['message' => $pesan],
        ], $status);
    }
}
