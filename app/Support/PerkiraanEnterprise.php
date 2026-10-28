<?php

namespace App\Support;

/**
 * Menyusun perkiraan harga Enterprise dari komponennya.
 *
 * Ada sebagai kelas tersendiri karena hitungan yang sama dipakai di **dua
 * tempat**: kalkulator di halaman Enterprise menghitungnya di peramban supaya
 * angkanya berubah seketika, dan server menghitungnya lagi saat permintaan
 * masuk. Kalau keduanya menyimpang, pengunjung melihat satu angka lalu tim
 * menerima angka lain — dan yang menemukan selisihnya adalah orang yang sudah
 * telanjur menyebut angka pertama di percakapan.
 *
 * Kelas ini sumber kebenarannya; sisi peramban membaca komponennya dari
 * `komponen()` yang sama, bukan menyalin angkanya ke dalam JavaScript.
 *
 * **Ini PERKIRAAN, bukan penawaran.** Yang mengikat tetap kesepakatan yang
 * disusun admin di panel — dan halaman itu menyebutkannya apa adanya, karena
 * angka yang tampak pasti lalu berubah saat ditagihkan adalah janji yang
 * dilanggar di hadapan orang yang baru saja memutuskan membeli.
 */
class PerkiraanEnterprise
{
    /**
     * Angka satuan yang dipakai kedua sisi.
     *
     * @return array<string, int>
     */
    public static function komponen(): array
    {
        $c = config('billing.enterprise');

        return [
            'base' => (int) $c['base'],
            'per_session' => (int) $c['per_session'],
            'included_sessions' => (int) $c['included_sessions'],
            'per_message_block' => (int) $c['per_message_block'],
            'message_block_size' => (int) $c['message_block_size'],
            'included_messages' => (int) $c['included_messages'],
            'retention_24_months' => (int) $c['retention_24_months'],
            'api_600_per_minute' => (int) $c['api_600_per_minute'],
            'onboarding' => (int) $c['onboarding'],
        ];
    }

    /**
     * Menghitung perkiraan untuk satu susunan kebutuhan.
     *
     * `onboarding` dipisahkan dari total bulanan dan TIDAK dijumlahkan ke
     * dalamnya: ia dibayar sekali. Menjumlahkannya membuat angka bulanan
     * terbaca lebih mahal dari yang sebenarnya, dan itu menghilangkan
     * pelanggan yang sebenarnya mampu.
     *
     * @return array{
     *     rincian: array<int, array{label: string, jumlah: int, catatan: string|null}>,
     *     bulanan: int,
     *     tahunan: int,
     *     sekali: int,
     * }
     */
    public static function hitung(
        int $nomor,
        int $pesan,
        bool $retensi24 = false,
        bool $api600 = false,
        bool $pendampingan = false,
    ): array {
        $k = self::komponen();

        // Dibatasi supaya angka yang mustahil — nol nomor, minus pesan —
        // tidak menghasilkan total yang terlihat masuk akal.
        $nomor = max(1, min($nomor, 100));
        $pesan = max(0, min($pesan, 100_000_000));

        $rincian = [[
            'label' => 'Dasar Enterprise',
            'jumlah' => $k['base'],
            'catatan' => 'Termasuk '.$k['included_sessions'].' nomor, '
                .number_format($k['included_messages'], 0, ',', '.').' pesan, retensi 12 bulan, '
                .'API key dan anggota tanpa batas',
        ]];

        $nomorTambahan = max(0, $nomor - $k['included_sessions']);

        if ($nomorTambahan > 0) {
            $rincian[] = [
                'label' => $nomorTambahan.' nomor WhatsApp tambahan',
                'jumlah' => $nomorTambahan * $k['per_session'],
                'catatan' => 'Rp '.number_format($k['per_session'], 0, ',', '.').' per nomor',
            ];
        }

        // Dibulatkan ke ATAS: pemakaian 60.000 pesan butuh dua blok, bukan
        // satu koma dua. Membulatkan ke bawah berarti menjanjikan kuota yang
        // tidak dibayar.
        $blok = (int) ceil(max(0, $pesan - $k['included_messages']) / $k['message_block_size']);

        if ($blok > 0) {
            $rincian[] = [
                'label' => $blok.' × '.number_format($k['message_block_size'], 0, ',', '.').' pesan tambahan',
                'jumlah' => $blok * $k['per_message_block'],
                'catatan' => 'Rp '.number_format($k['per_message_block'], 0, ',', '.').' per blok',
            ];
        }

        if ($retensi24) {
            $rincian[] = [
                'label' => 'Retensi riwayat 24 bulan',
                'jumlah' => $k['retention_24_months'],
                'catatan' => 'Dari 12 bulan bawaan',
            ];
        }

        if ($api600) {
            $rincian[] = [
                'label' => 'Batas API 600 permintaan/menit',
                'jumlah' => $k['api_600_per_minute'],
                'catatan' => 'Dari 300 bawaan',
            ];
        }

        $bulanan = array_sum(array_column($rincian, 'jumlah'));

        return [
            'rincian' => $rincian,
            'bulanan' => $bulanan,
            // Pengali yang sama dengan paket katalog: bayar sepuluh bulan
            // untuk dua belas. Angka yang berbeda di sini akan ditanyakan.
            'tahunan' => $bulanan * (int) config('plans.yearly_multiplier'),
            'sekali' => $pendampingan ? $k['onboarding'] : 0,
        ];
    }
}
