<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menjaga endpoint /internal/* yang hanya boleh dipanggil engine Node.
 *
 * Dua lapis: timestamp untuk menolak pemutaran ulang payload lama, dan HMAC
 * atas timestamp + isi body. Tanpa timestamp, penyerang yang pernah menangkap
 * satu callback "session connected" bisa memutarnya kapan saja.
 */
class VerifyEngineSignature
{
    private const MAX_SKEW_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('gateway.engine.hmac_secret');

        if (! $secret) {
            Log::error('ENGINE_HMAC_SECRET belum diisi; endpoint internal ditolak.');

            return $this->deny('Endpoint internal belum dikonfigurasi.');
        }

        $signature = $request->header('X-Engine-Signature');
        $timestamp = $request->header('X-Engine-Timestamp');

        if (! $signature || ! $timestamp) {
            return $this->deny('Tanda tangan tidak lengkap.');
        }

        if (abs(now()->getTimestamp() - (int) $timestamp) > self::MAX_SKEW_SECONDS) {
            return $this->deny('Tanda tangan sudah kedaluwarsa.');
        }

        // Unggahan backup sesi bisa puluhan MB. Untuk request seperti itu engine
        // mengirim sha256 isi file di header dan menandatangani hash-nya, supaya
        // Laravel bisa memverifikasi tanda tangan lalu menulis file secara
        // streaming tanpa pernah menampung seluruh isinya di memori. Isi file
        // tetap terjamin karena hash-nya dicocokkan ulang saat ditulis.
        $digest = $request->header('X-Engine-Body-Sha256');

        $signed = $digest !== null
            ? $timestamp.'.'.$request->path().'.'.$digest
            : $timestamp.'.'.$request->getContent();

        if (! hash_equals(hash_hmac('sha256', $signed, $secret), $signature)) {
            return $this->deny('Tanda tangan tidak cocok.');
        }

        return $next($request);
    }

    private function deny(string $message): Response
    {
        return response()->json([
            'success' => false,
            'error' => ['message' => $message],
        ], 401);
    }
}
