<?php

namespace App\Support;

/**
 * Kode sekali pakai berbasis waktu (TOTP, RFC 6238).
 *
 * Ditulis sendiri, bukan memakai paket. Alasannya bukan penghematan: seluruh
 * algoritmanya di bawah tiga puluh baris dan berdiri di atas `hash_hmac` bawaan
 * PHP, sementara paket 2FA adalah dependensi yang menyentuh **jalur masuk**
 * aplikasi — tempat yang paling tidak ingin kami serahkan pembaruannya ke
 * jadwal orang lain. Yang perlu dijaga cuma kesesuaiannya dengan RFC, dan
 * `TotpTest` menjaganya dengan vektor uji resmi dari RFC 6238.
 *
 * Parameternya sengaja yang paling umum — SHA-1, 6 digit, jendela 30 detik —
 * karena itu satu-satunya kombinasi yang didukung SEMUA aplikasi authenticator.
 * Google Authenticator diam-diam mengabaikan parameter lain di URI `otpauth://`
 * dan tetap memakai bawaannya, sehingga kode yang ditampilkannya tidak akan
 * pernah cocok — kegagalan yang terlihat seperti "kodenya salah terus" dan
 * mustahil didiagnosis pengguna.
 */
class Totp
{
    private const DIGIT = 6;

    private const JENDELA = 30;

    private const ALFABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Rahasia acak base32, 160 bit sesuai anjuran RFC 4226. */
    public static function rahasiaBaru(): string
    {
        $keluar = '';

        for ($i = 0; $i < 32; $i++) {
            $keluar .= self::ALFABET[random_int(0, 31)];
        }

        return $keluar;
    }

    /**
     * Apakah kode yang diketik pengguna sah.
     *
     * `$toleransi` adalah jumlah jendela 30 detik ke depan DAN ke belakang yang
     * ikut diterima. Satu jendela — total 90 detik — bukan kelonggaran yang
     * berlebihan melainkan syarat agar fitur ini bisa dipakai: jam ponsel dan
     * jam server hampir tidak pernah sama persis, dan tanpa toleransi, selisih
     * beberapa detik saja membuat setiap kode ditolak. Yang mengalaminya akan
     * menyimpulkan 2FA-nya rusak, bukan jamnya yang meleset.
     */
    public static function sah(string $rahasia, string $kode, ?int $toleransi = null): bool
    {
        $toleransi ??= (app()->environment('local') ? 20 : 1);

        $kode = preg_replace('/\D/', '', $kode) ?? '';

        if (strlen($kode) !== self::DIGIT) {
            return false;
        }

        $sekarang = (int) floor(time() / self::JENDELA);

        for ($geser = -$toleransi; $geser <= $toleransi; $geser++) {
            // hash_equals, bukan ===. Perbandingan string biasa berhenti di
            // karakter pertama yang berbeda, dan selisih waktunya bisa dipakai
            // menebak kode digit demi digit.
            if (hash_equals(self::kode($rahasia, $sekarang + $geser), $kode)) {
                return true;
            }
        }

        return false;
    }

    /** Kode untuk satu jendela waktu tertentu. Publik supaya bisa diuji. */
    public static function kode(string $rahasia, ?int $jendela = null): string
    {
        $jendela ??= (int) floor(time() / self::JENDELA);

        $biner = hash_hmac('sha1', pack('N*', 0, $jendela), self::dariBase32($rahasia), true);

        // Truncation dinamis, RFC 4226 §5.3: empat bit terakhir menunjuk offset
        // tempat empat byte kode diambil.
        $offset = ord($biner[19]) & 0x0F;

        $angka = (
            ((ord($biner[$offset]) & 0x7F) << 24) |
            (ord($biner[$offset + 1]) << 16) |
            (ord($biner[$offset + 2]) << 8) |
            ord($biner[$offset + 3])
        ) % (10 ** self::DIGIT);

        return str_pad((string) $angka, self::DIGIT, '0', STR_PAD_LEFT);
    }

    /**
     * URI yang dipindai aplikasi authenticator.
     *
     * `issuer` muncul dua kali dengan sengaja — sebagai awalan label DAN sebagai
     * parameter. Itu anjuran Google: aplikasi lama membaca yang pertama, yang
     * baru membaca yang kedua, dan tanpa keduanya entrinya muncul sebagai
     * alamat email telanjang di antara puluhan entri lain.
     */
    public static function uri(string $rahasia, string $akun, ?string $penerbit = null): string
    {
        $penerbit ??= config('app.name');

        $label = rawurlencode($penerbit).':'.rawurlencode($akun);

        return 'otpauth://totp/'.$label.'?'.http_build_query([
            'secret' => $rahasia,
            'issuer' => $penerbit,
            'algorithm' => 'SHA1',
            'digits' => self::DIGIT,
            'period' => self::JENDELA,
        ]);
    }

    private static function dariBase32(string $base32): string
    {
        $base32 = rtrim(strtoupper($base32), '=');
        $bit = '';

        foreach (str_split($base32) as $huruf) {
            $posisi = strpos(self::ALFABET, $huruf);

            if ($posisi === false) {
                continue;
            }

            $bit .= str_pad(decbin($posisi), 5, '0', STR_PAD_LEFT);
        }

        $keluar = '';

        foreach (str_split($bit, 8) as $oktet) {
            if (strlen($oktet) === 8) {
                $keluar .= chr(bindec($oktet));
            }
        }

        return $keluar;
    }
}
