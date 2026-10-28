<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use WeakMap;

/**
 * Menerjemahkan kegagalan engine menjadi kalimat yang boleh dibaca pelanggan.
 *
 * Sebelum ini pesan mentahnya dilempar apa adanya ke layar:
 *
 *   "Gagal menghubungi engine: cURL error 7: Failed to connect to 127.0.0.1
 *    port 3100 after 2034 ms: Couldn't connect to server (see
 *    https://curl.se/libcurl/c/libcurl-errors.html) for
 *    http://127.0.0.1:3100/sessions/01m1ttj.../start"
 *
 *   Atau:
 *   "json_encode error: Malformed UTF-8 characters, possibly incorrectly encoded"
 *
 * Tiga hal salah sekaligus di situ:
 * 1. Pelanggan tidak tahu apa itu "engine", "cURL", "json_encode", atau port 3100.
 * 2. Membocorkan arsitektur internal, nama pustaka, alamat IP, dan ULID.
 * 3. Tidak memberi tahu apa yang terjadi pada nomornya dan apa yang harus dilakukan.
 *
 * Rincian teknis dicatat di log aplikasi bersama kode rujukan singkat (mis. REF-A1B2C3).
 * Pelanggan hanya melihat pesan sopan yang menjelaskan apa yang terjadi, apa yang
 * bisa dia lakukan, dan kapan harus menghubungi bantuan dengan menyebut kode tersebut.
 */
class EngineError
{
    private static ?WeakMap $rujukanMap = null;

    /**
     * Menghasilkan kode rujukan unik singkat per exception.
     * Menggunakan WeakMap agar pemanggilan berulang untuk exception yang sama
     * menghasilkan kode rujukan yang konsisten tanpa kebocoran memori.
     */
    public static function kodeRujukan(Throwable $e): string
    {
        if (self::$rujukanMap === null) {
            self::$rujukanMap = new WeakMap;
        }

        if (! isset(self::$rujukanMap[$e])) {
            self::$rujukanMap[$e] = 'REF-'.strtoupper(Str::random(6));
        }

        return self::$rujukanMap[$e];
    }

    /**
     * Kalimat untuk pelanggan. Selalu menyebut apa yang terjadi pada nomornya,
     * karena itu satu-satunya pertanyaan yang benar-benar ada di kepala orang
     * saat layar ini muncul.
     */
    public static function pesan(Throwable $e): string
    {
        if ($e instanceof ConnectionException) {
            return 'Layanan WhatsApp sedang tidak bisa dihubungi. '
                .'Nomor yang sudah tertaut tidak terputus dan tidak perlu discan ulang — '
                .'coba lagi beberapa menit lagi.';
        }

        $asli = trim($e->getMessage());

        // Batas kapasitas engine disebut dengan nama variabel envnya; itu
        // informasi untuk kami, bukan untuk pelanggan yang cuma perlu tahu
        // bahwa ini antrean, bukan kesalahan mereka.
        if (str_contains($asli, 'WA_MAX_SESSIONS') || str_contains($asli, 'batas')) {
            return 'Kapasitas nomor aktif sedang penuh. '
                .'Tim kami sudah diberi tahu — coba lagi beberapa saat lagi.';
        }

        // Pesan yang memang sudah ditulis untuk dibaca manusia dan tidak
        // membocorkan apa pun boleh lewat apa adanya. Patokannya sederhana dan
        // sengaja konservatif: tidak ada jejak teknis di dalamnya.
        if ($asli !== '' && ! self::teknis($asli)) {
            return $asli;
        }

        $rujukan = self::kodeRujukan($e);

        Log::warning("Galat layanan WhatsApp [rujukan: {$rujukan}]: {$asli}", [
            'rujukan' => $rujukan,
            'exception' => $e::class,
            'file' => $e->getFile().':'.$e->getLine(),
            'message' => $asli,
        ]);

        return 'Layanan WhatsApp gagal menjalankan permintaan ini. '
            .'Nomor yang sudah tertaut tidak terpengaruh. Coba lagi beberapa saat lagi. '
            ."Kalau berulang, hubungi bantuan (kode: {$rujukan}).";
    }

    /**
     * Kalimat yang memuat jejak teknis: alamat, port, nama pustaka, kode galat,
     * pesan runtime programmer, atau dump struktur data.
     *
     * Daftarnya konservatif — apa pun yang berbau teknis atau tidak ramah pengguna
     * ditandai teknis dan jatuh ke kalimat umum dengan kode rujukan.
     */
    public static function teknis(string $pesan): bool
    {
        $jejak = [
            'cURL',
            'curl',
            'http://',
            'https://',
            '127.0.0.1',
            'localhost',
            'port ',
            ':3100',
            'Exception',
            'Error',
            'error:',
            'SQLSTATE',
            'SQL',
            'PDO',
            'json_encode',
            'json_decode',
            'UTF-8',
            'Malformed',
            'Stack trace',
            '#0 ',
            'Call to ',
            'undefined',
            'syntax error',
            'Parse error',
            'Fatal error',
            'TypeError',
            'ValueError',
            'ArgumentCountError',
            'file_get_contents',
            'stream_copy_to_stream',
            'fopen',
            'fclose',
            'stream',
            'timed out',
            'timeout',
            'Connection refused',
            'ECONNREFUSED',
            'ENOENT',
            'ETIMEDOUT',
            'must be of type',
            'null given',
            'array given',
            'vendor/',
            '.php',
            '.js',
        ];

        foreach ($jejak as $kata) {
            if (stripos($pesan, $kata) !== false) {
                return true;
            }
        }

        if (str_contains($pesan, '{') || str_contains($pesan, '}')) {
            return true;
        }

        return false;
    }
}
