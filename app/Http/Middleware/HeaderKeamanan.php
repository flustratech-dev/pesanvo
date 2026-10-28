<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan yang dipasang pada seluruh respons.
 *
 * Yang ditutup di sini bukan lubang di kode kami melainkan asumsi bawaan
 * browser: tanpa HSTS, satu permintaan `http://` sebelum redirect sudah cukup
 * membocorkan cookie sesi di jaringan yang sama; tanpa `X-Frame-Options`,
 * dashboard bisa dibingkai halaman lain dan tombolnya ditumpangi (clickjacking);
 * tanpa `nosniff`, berkas yang diunggah pelanggan bisa ditebak jenisnya oleh
 * browser dan dijalankan sebagai sesuatu yang bukan maksudnya.
 *
 * **Yang sengaja tidak ada di sini: Content-Security-Policy.** Dashboard memakai
 * Alpine dan skrip inline di banyak tempat; CSP yang salah tidak menghasilkan
 * galat yang terlihat — ia cuma membuat seluruh antarmuka berhenti bereaksi,
 * dan yang menemukannya pelanggan. CSP butuh `nonce` di setiap skrip inline dan
 * itu pekerjaan tersendiri.
 */
class HeaderKeamanan
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // HSTS HANYA saat sambungannya benar-benar HTTPS. Header ini membuat
        // browser MENOLAK http:// untuk host itu selama setahun, dan sekali
        // tersimpan ia tidak bisa dibatalkan dengan menghapus kode — pengembang
        // yang terkena harus membersihkannya sendiri dari setelan browsernya.
        // Di lokal (http://127.0.0.1:8051) itu berarti aplikasi tidak bisa
        // dibuka sama sekali.
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // `strict-origin-when-cross-origin`: alamat lengkap hanya dikirim ke
        // diri sendiri. Tanpa ini, tautan keluar dari halaman seperti
        // /admin/workspaces/{id} membocorkan id workspace pelanggan ke situs
        // tujuan lewat header Referer.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Tidak satu pun halaman di produk ini butuh kamera, mikrofon, atau
        // lokasi. Menyebutkannya kosong berarti skrip pihak ketiga yang
        // menyusup pun tidak bisa memintanya.
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        return $response;
    }
}
