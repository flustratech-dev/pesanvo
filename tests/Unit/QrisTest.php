<?php

namespace Tests\Unit;

use App\Support\Qris;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * QRIS dinamis.
 *
 * Kegagalan di kelas ini punya bentuk yang khas dan mahal: kode QR-nya tetap
 * tergambar, tetap tampak wajar, dan baru ketahuan salah saat pelanggan sudah
 * berdiri di depan layar dengan aplikasi banknya terbuka. Karena itu yang diuji
 * bukan "menghasilkan string", melainkan CRC-nya benar-benar cocok.
 */
class QrisTest extends TestCase
{
    /**
     * Payload statis contoh, disusun sendiri dengan struktur EMVCo yang sama
     * dengan QRIS asli: tag 00 (versi), 01 (statis), 26 (identitas merchant),
     * 52-53 (kategori & mata uang), 58-60 (negara, nama, kota), 63 (CRC).
     */
    private function payloadStatis(): string
    {
        $isi = '000201'
            .'010211'
            .'26370014ID.CO.QRIS.WWW0215ID1234567890123'
            .'52045812'
            .'5303360'
            .'5802ID'
            .'5908PESANVO'
            .'6007JAKARTA'
            .'6304';

        return $isi.$this->crc($isi);
    }

    private function crc(string $data): string
    {
        $crc = 0xFFFF;

        for ($i = 0, $n = strlen($data); $i < $n; $i++) {
            $crc ^= ord($data[$i]) << 8;

            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    public function test_payload_contoh_memang_sah(): void
    {
        // Kalau contohnya sendiri tidak sah, seluruh tes di bawah ini menguji
        // sesuatu yang tidak pernah bisa dipakai.
        $this->assertTrue(Qris::valid($this->payloadStatis()));
    }

    /**
     * Batas yang perlu diketahui: `valid()` memeriksa CRC, dan CRC dihitung
     * atas string mentah tanpa memedulikan struktur TLV di dalamnya. Payload
     * yang panjang tag-nya salah tulis tetap lolos di sini — kesalahan itu baru
     * terlihat saat isinya dibaca, seperti pada tes identitas merchant di bawah.
     */
    public function test_panjang_tag_yang_salah_lolos_pemeriksaan_crc(): void
    {
        // Panjang tag 26 dikurangi satu; isinya tidak berubah, jadi CRC-nya
        // dihitung ulang atas string yang tetap "utuh" menurut ukurannya sendiri.
        $rusak = str_replace('2637', '2636', $this->payloadStatis());
        $rusak = substr($rusak, 0, -4);
        $rusak .= $this->crc($rusak);

        $this->assertTrue(Qris::valid($rusak), 'CRC memang tidak melihat struktur TLV.');

        // Dan inilah akibatnya saat isinya benar-benar dibaca: blok identitas
        // merchant tidak lagi utuh sebagai satu tag. Karakter-karakternya bisa
        // saja masih berjajar di string karena tag berikutnya ikut tergeser —
        // yang hilang adalah strukturnya, dan aplikasi bank membaca struktur.
        $this->assertStringNotContainsString(
            '26370014ID.CO.QRIS.WWW0215ID1234567890123',
            Qris::dinamis($rusak, 5_000)
        );
    }

    public function test_hasilnya_lolos_pemeriksaan_crc(): void
    {
        $dinamis = Qris::dinamis($this->payloadStatis(), 149_037);

        $this->assertTrue(
            Qris::valid($dinamis),
            'CRC tidak cocok — setiap aplikasi bank akan menolak kode ini.'
        );
    }

    public function test_nominal_tersisip_di_tag_54(): void
    {
        $dinamis = Qris::dinamis($this->payloadStatis(), 149_037);

        // 54 = tag nominal, 06 = panjang "149037".
        $this->assertStringContainsString('5406149037', $dinamis);
    }

    public function test_penanda_sekali_pakai_diaktifkan(): void
    {
        $dinamis = Qris::dinamis($this->payloadStatis(), 25_000);

        // Tag 01 harus berubah dari 11 (statis) menjadi 12 (dinamis). Tanpa itu
        // sebagian aplikasi mengabaikan nominal dan meminta pengguna mengetik
        // sendiri — persis yang mekanisme ini dibuat untuk menghindari.
        $this->assertStringStartsWith('000201010212', $dinamis);
    }

    public function test_identitas_merchant_tidak_berubah(): void
    {
        $dinamis = Qris::dinamis($this->payloadStatis(), 1_000);

        // Blok 26 adalah bagian payload yang paling rewel diterima aplikasi
        // bank; ia harus berpindah apa adanya, bukan disusun ulang.
        $this->assertStringContainsString('26370014ID.CO.QRIS.WWW0215ID1234567890123', $dinamis);
    }

    public function test_nominal_yang_sudah_ada_ditimpa_bukan_digandakan(): void
    {
        $sekali = Qris::dinamis($this->payloadStatis(), 149_000);
        $dua_kali = Qris::dinamis($sekali, 249_000);

        $this->assertTrue(Qris::valid($dua_kali));
        $this->assertStringContainsString('5406249000', $dua_kali);
        $this->assertStringNotContainsString('149000', $dua_kali);
    }

    public function test_payload_yang_bukan_emvco_ditolak(): void
    {
        $this->expectException(RuntimeException::class);

        Qris::dinamis('bukan payload qris sama sekali', 10_000);
    }

    public function test_nominal_nol_ditolak(): void
    {
        $this->expectException(RuntimeException::class);

        Qris::dinamis($this->payloadStatis(), 0);
    }

    public function test_payload_dengan_crc_salah_tidak_dianggap_sah(): void
    {
        $rusak = substr($this->payloadStatis(), 0, -4).'0000';

        $this->assertFalse(Qris::valid($rusak));
    }
}
