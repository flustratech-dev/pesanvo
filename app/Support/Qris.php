<?php

namespace App\Support;

use RuntimeException;

/**
 * Menyisipkan nominal ke dalam payload QRIS statis, menghasilkan QRIS dinamis.
 *
 * QRIS memakai format EMVCo: rangkaian blok `TTLLVV` — dua digit tag, dua digit
 * panjang, lalu isinya sepanjang itu. Blok terakhir selalu tag 63, yaitu CRC
 * dari seluruh string sebelumnya termasuk `6304` milik CRC itu sendiri.
 *
 * Yang diubah untuk membuatnya dinamis cuma tiga hal:
 *
 *   tag 01  →  `12`, menandai kode ini sekali pakai dengan nominal tetap
 *   tag 54  →  nominal, disisipkan atau ditimpa
 *   tag 63  →  CRC dihitung ulang
 *
 * Kenapa repot dibanding menyuruh pelanggan mengetik nominal sendiri: nominal
 * kami memuat kode unik tiga digit yang menjadi satu-satunya cara mencocokkan
 * pembayaran dengan tagihan. Satu digit salah ketik berarti pembayaran yang
 * masuk tapi tidak bisa dikenali milik siapa — dan pelanggan yang sudah bayar
 * tapi layanannya belum menyala.
 *
 * Tag disusun ulang menaik saat dirangkai kembali. Payload QRIS yang sah
 * memang sudah datang dalam urutan itu, jadi bagi payload normal ini tidak
 * mengubah apa pun — gunanya menempatkan tag 54 yang baru disisipkan di posisi
 * yang benar tanpa perlu mencari-cari sendiri di mana seharusnya.
 */
class Qris
{
    /** Penanda "sekali pakai" pada tag 01. Statis bernilai `11`. */
    private const DINAMIS = '12';

    /**
     * @param  string  $statis  Payload QRIS statis dari penyedia merchant.
     * @param  int  $nominal  Jumlah rupiah penuh, tanpa desimal.
     */
    public static function dinamis(string $statis, int $nominal): string
    {
        $statis = trim($statis);

        if ($nominal < 1) {
            throw new RuntimeException('Nominal QRIS harus lebih dari nol.');
        }

        $blok = self::urai($statis);

        // Tanpa tag 00 dan 01 ini bukan payload EMVCo. Lebih baik gagal di sini
        // daripada menghasilkan kode QR yang tampak wajar tapi ditolak semua
        // aplikasi bank saat pelanggan sudah berdiri di depan layar.
        if (! isset($blok['00'])) {
            throw new RuntimeException('Payload QRIS tidak dikenali: tag 00 tidak ada.');
        }

        $blok['01'] = self::DINAMIS;
        $blok['54'] = (string) $nominal;

        // CRC lama tidak berlaku lagi begitu isinya berubah, dan membiarkannya
        // ikut tersusun ulang akan menghasilkan dua tag 63.
        unset($blok['63']);

        $payload = self::susun($blok);

        return $payload.'6304'.self::crc16($payload.'6304');
    }

    /**
     * Memeriksa apakah sebuah payload utuh — CRC di ujungnya cocok dengan isinya.
     * Dipakai untuk memvalidasi payload yang dipasang lewat env sebelum ia
     * sempat tampil sebagai kode QR rusak di halaman pembayaran.
     */
    public static function valid(string $payload): bool
    {
        $payload = trim($payload);

        if (strlen($payload) < 8 || ! str_contains($payload, '6304')) {
            return false;
        }

        $tanpaCrc = substr($payload, 0, -4);
        $crc = substr($payload, -4);

        return strtoupper($crc) === self::crc16($tanpaCrc);
    }

    /**
     * Mengurai payload menjadi peta tag => isi.
     *
     * Sub-tag di dalam tag 26-51 (identitas merchant) tidak ikut diurai: isinya
     * cukup dipindahkan apa adanya, dan mengurainya berarti menulis ulang
     * bagian payload yang paling rewel diterima aplikasi bank.
     *
     * @return array<string, string>
     */
    private static function urai(string $payload): array
    {
        $blok = [];
        $i = 0;
        $panjangTotal = strlen($payload);

        while ($i + 4 <= $panjangTotal) {
            $tag = substr($payload, $i, 2);
            $panjang = (int) substr($payload, $i + 2, 2);

            // Panjang yang menunjuk melewati ujung string berarti payload-nya
            // terpotong. Berhenti di sini, biarkan pemeriksaan tag 00 di atas
            // yang memutuskan apakah sisanya masih layak dipakai.
            if ($panjang <= 0 || $i + 4 + $panjang > $panjangTotal) {
                break;
            }

            $blok[$tag] = substr($payload, $i + 4, $panjang);
            $i += 4 + $panjang;
        }

        return $blok;
    }

    /** @param  array<string, string>  $blok */
    private static function susun(array $blok): string
    {
        ksort($blok, SORT_STRING);

        $payload = '';

        foreach ($blok as $tag => $isi) {
            $payload .= $tag.str_pad((string) strlen($isi), 2, '0', STR_PAD_LEFT).$isi;
        }

        return $payload;
    }

    /**
     * CRC-16/CCITT-FALSE — polinomial 0x1021, nilai awal 0xFFFF, tanpa
     * pembalikan bit dan tanpa XOR akhir. Varian inilah yang dipakai EMVCo;
     * varian CRC-16 lain menghasilkan empat digit yang tampak wajar tapi
     * membuat setiap aplikasi bank menolak kodenya.
     */
    private static function crc16(string $data): string
    {
        $crc = 0xFFFF;

        for ($i = 0, $n = strlen($data); $i < $n; $i++) {
            $crc ^= ord($data[$i]) << 8;

            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000)
                    ? (($crc << 1) ^ 0x1021)
                    : ($crc << 1);

                $crc &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
