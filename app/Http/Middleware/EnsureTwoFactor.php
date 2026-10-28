<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menahan sesi yang belum melewati faktor kedua.
 *
 * Dipasang pada seluruh halaman terautentikasi, bukan hanya panel admin. Kalau
 * hanya panel admin yang dijaga, akun admin yang kata sandinya bocor tetap bisa
 * dipakai membuka dashboard, mengekspor data, membuat API key, dan mengirim
 * pesan — seluruhnya di luar panel, seluruhnya tanpa faktor kedua.
 *
 * ## Dua keadaan yang ditangani, dan urutannya penting
 *
 * 1. **Sudah menyalakan 2FA tapi belum menjawab tantangan** → ke layar kode.
 * 2. **Wajib memakai 2FA tapi belum memasangnya** → ke layar pemasangan.
 *
 * Yang kedua tidak boleh dilewati. Tanpa pemaksaan itu, "2FA wajib untuk admin"
 * hanya berlaku bagi admin yang kebetulan memasangnya sendiri — dan yang paling
 * mungkin tidak memasangnya adalah admin yang paling lama tidak menyentuh
 * pengaturan keamanan.
 *
 * Rute 2FA itu sendiri dan `logout` dikecualikan; tanpa pengecualian itu
 * middleware ini mengarahkan ke halaman yang ia jaga sendiri, dan peramban
 * berputar sampai menyerah.
 */
class EnsureTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $request->routeIs('two-factor.*', 'logout')) {
            return $next($request);
        }

        if ($user->duaFaktorAktif() && ! $request->session()->get('2fa.lolos')) {
            return $this->alihkan($request, 'two-factor.challenge');
        }

        if ($user->wajibDuaFaktor() && ! $user->duaFaktorAktif()) {
            return $this->alihkan($request, 'two-factor.setup');
        }

        return $next($request);
    }

    /**
     * Permintaan JSON dijawab 403, bukan dialihkan.
     *
     * Pengalihan 302 ke halaman HTML pada panggilan `fetch` dari dashboard
     * menghasilkan galat parsing JSON di peramban — kegagalan yang terbaca
     * seperti kerusakan aplikasi, bukan seperti "Anda perlu memasukkan kode".
     */
    private function alihkan(Request $request, string $rute): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => ['message' => 'Autentikasi dua faktor diperlukan.']], 403);
        }

        return redirect()->route($rute);
    }
}
