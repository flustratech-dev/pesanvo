<?php

namespace App\Support;

use App\Models\StatusHarian;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Keadaan tiap komponen layanan, untuk halaman status publik.
 *
 * Halaman status ada supaya pelanggan bisa menjawab sendiri satu pertanyaan
 * yang selama ini hanya bisa dijawab dengan mengirim tiket: **ini rusak di saya
 * atau di mereka?** Tanpa halaman itu, setiap gangguan menghasilkan puluhan
 * tiket yang isinya sama, dan yang menjawabnya orang yang sedang sibuk
 * memperbaiki gangguan tersebut.
 *
 * ## Batas yang harus disebut, bukan disembunyikan
 *
 * Halaman ini disajikan oleh aplikasi yang statusnya sedang ia laporkan. Kalau
 * containernya mati total, halaman ini ikut mati — dan pelanggan melihat layar
 * galat, bukan tulisan "sedang gangguan". Jadi halaman ini **tidak pernah bisa
 * membuktikan bahwa sistem hidup**; yang bisa dibuktikannya hanya bahwa sebagian
 * sistem sedang tidak sehat.
 *
 * Yang menutup lubang itu ada di luar: `RekamStatusJob` mengirim denyut ke
 * pemantau eksternal tiap menit, dan pemantau itulah yang menyadari kalau
 * denyutnya berhenti. Sesuatu di dalam container tidak bisa melaporkan
 * kematiannya sendiri.
 */
class StatusLayanan
{
    public const OPERASIONAL = 'operasional';

    public const TERGANGGU = 'terganggu';

    public const MATI = 'mati';

    /**
     * Urutannya disengaja: dari yang paling dekat dengan pelanggan ke yang
     * paling dalam. Yang membuka halaman ini hampir selalu mencari satu
     * komponen tertentu, dan yang dicari biasanya ada di atas.
     *
     * @return array<string, array{nama: string, jelas: string}>
     */
    public static function daftarKomponen(): array
    {
        return [
            'api' => [
                'nama' => 'REST API',
                'jelas' => 'Endpoint /api/v1 yang dipanggil aplikasi Anda.',
            ],
            'dashboard' => [
                'nama' => 'Dashboard',
                'jelas' => 'Halaman web untuk mengelola nomor, pesan, dan langganan.',
            ],
            'whatsapp' => [
                'nama' => 'Koneksi WhatsApp',
                'jelas' => 'Engine yang memegang sambungan ke jaringan WhatsApp.',
            ],
            'antrean' => [
                'nama' => 'Antrean pengiriman',
                'jelas' => 'Pekerja yang benar-benar mengirim pesan dan webhook.',
            ],
            'basis_data' => [
                'nama' => 'Basis data',
                'jelas' => 'Penyimpanan pesan, sesi, dan langganan.',
            ],
        ];
    }

    /**
     * Memeriksa seluruh komponen sekarang juga.
     *
     * @return array<string, array{nama: string, jelas: string, keadaan: string, catatan: string}>
     */
    public static function periksa(): array
    {
        $hasil = [];

        foreach (self::daftarKomponen() as $kunci => $meta) {
            $hasil[$kunci] = $meta + match ($kunci) {
                'whatsapp' => self::periksaEngine(),
                'antrean' => self::periksaAntrean(),
                'basis_data' => self::periksaBasisData(),

                // API dan dashboard dijawab oleh proses yang sedang merender
                // halaman ini. Kalau keduanya benar-benar mati, permintaan ini
                // tidak akan pernah sampai ke sini — jadi menandainya hijau
                // bukan kepalsuan, melainkan satu-satunya yang bisa
                // disimpulkan dengan jujur dari dalam.
                default => ['keadaan' => self::OPERASIONAL, 'catatan' => 'Menjawab normal.'],
            };
        }

        return $hasil;
    }

    /** Ringkasan satu kalimat untuk spanduk paling atas. */
    public static function ringkas(array $komponen): string
    {
        $keadaan = array_column($komponen, 'keadaan');

        if (in_array(self::MATI, $keadaan, true)) {
            return self::MATI;
        }

        return in_array(self::TERGANGGU, $keadaan, true) ? self::TERGANGGU : self::OPERASIONAL;
    }

    /**
     * Mencatat hasil pemeriksaan sebagai satu sampel harian.
     *
     * Yang disimpan **hitungan per hari**, bukan satu baris per pemeriksaan.
     * Menyimpan mentahnya berarti 5 komponen × 1.440 menit × 90 hari = 648.000
     * baris yang seluruhnya hanya dipakai untuk menggambar 90 batang kecil —
     * beban tulis tiap menit selamanya demi angka yang sudah cukup akurat kalau
     * dijumlahkan sejak awal.
     */
    public static function catat(array $komponen): void
    {
        $hari = now()->toDateString();

        foreach ($komponen as $kunci => $data) {
            $baris = StatusHarian::firstOrCreate(
                ['component' => $kunci, 'day' => $hari],
                // Ditulis eksplisit: baris baru dari firstOrCreate tidak membaca
                // nilai default database, dan counter yang terbaca null bukan 0
                // membuat seluruh hitungan uptime hari itu hilang.
                ['ok_count' => 0, 'fail_count' => 0],
            );

            $baris->increment($data['keadaan'] === self::OPERASIONAL ? 'ok_count' : 'fail_count');
        }
    }

    private static function periksaEngine(): array
    {
        try {
            $jawaban = Http::timeout((int) config('gateway.engine.connect_timeout', 5))
                ->withToken(config('gateway.engine.token'))
                ->get(rtrim(config('gateway.engine.url'), '/').'/health');

            if ($jawaban->successful()) {
                return ['keadaan' => self::OPERASIONAL, 'catatan' => 'Menjawab normal.'];
            }

            return [
                'keadaan' => self::MATI,
                'catatan' => 'Engine menjawab dengan status '.$jawaban->status().'.',
            ];
        } catch (\Throwable) {
            /*
             | Pesan galatnya SENGAJA tidak diteruskan ke halaman publik. Isinya
             | alamat dan port internal — `cURL error 7: Failed to connect to
             | 127.0.0.1 port 3100` — yang tidak bisa ditindaklanjuti pembacanya
             | dan membocorkan susunan jaringan kami ke layar yang bisa
             | di-screenshot siapa saja. Rinciannya tetap ada di /admin/sistem.
            */
            return [
                'keadaan' => self::MATI,
                'catatan' => 'Engine tidak dapat dihubungi. Pesan keluar tertahan sampai pulih.',
            ];
        }
    }

    private static function periksaAntrean(): array
    {
        try {
            $antrean = KesehatanAntrean::periksa();
        } catch (\Throwable) {
            return ['keadaan' => self::MATI, 'catatan' => 'Keadaan antrean tidak dapat dibaca.'];
        }

        if ($antrean['macet']) {
            return [
                'keadaan' => self::MATI,
                'catatan' => 'Pekerja antrean tidak bergerak. Pesan tertahan dan belum terkirim.',
            ];
        }

        /*
         | Antrean panjang BUKAN gangguan, dan membedakan keduanya adalah
         | seluruh gunanya bagian ini. Broadcast seribu nomor sah-sah saja
         | menyisakan pekerjaan berjam-jam karena tiap pesan ditahan 3–8 detik.
         | Menandainya merah melatih pembaca mengabaikan warna merah di halaman
         | yang cuma berguna selama merahnya masih berarti.
        */
        if ($antrean['menunggu'] > (int) config('monitoring.ambang.antrean_menunggu')) {
            return [
                'keadaan' => self::TERGANGGU,
                'catatan' => 'Antrean sedang panjang. Pesan tetap terkirim, tetapi lebih lambat dari biasanya.',
            ];
        }

        return ['keadaan' => self::OPERASIONAL, 'catatan' => 'Berjalan normal.'];
    }

    private static function periksaBasisData(): array
    {
        try {
            DB::connection()->getPdo()->query('SELECT 1');

            return ['keadaan' => self::OPERASIONAL, 'catatan' => 'Menjawab normal.'];
        } catch (\Throwable) {
            return ['keadaan' => self::MATI, 'catatan' => 'Basis data tidak dapat dihubungi.'];
        }
    }
}
