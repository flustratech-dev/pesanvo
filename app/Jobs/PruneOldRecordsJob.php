<?php

namespace App\Jobs;

use App\Support\Pemangkas;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Pemangkasan retensi terjadwal, tiap hari pukul 03:15.
 *
 * Seluruh logikanya ada di `App\Support\Pemangkas`, dipakai bersama perintah
 * `pesanvo:pangkas`. Disatukan dengan sengaja: dua salinan aturan retensi
 * berarti dua aturan yang suatu saat berbeda, dan yang berbeda diam-diam di
 * sini adalah data pelanggan yang terhapus lebih cepat dari yang dijanjikan
 * paketnya.
 *
 * Job ini dulu menjalankan dua `DELETE` tanpa batas — satu atas `messages`, satu
 * atas `webhook_deliveries`. Pada tabel yang sudah besar, keduanya menahan kunci
 * dan menggelembungkan undo log MySQL yang dipakai bersama aplikasi pesanvo, dan
 * gejalanya muncul di aplikasi yang tidak ada hubungannya dengan gateway ini.
 * Sekarang seluruhnya bertahap.
 *
 * `$timeout` 600 dan `retry_after` antrean 1200 (config/queue.php): job ini
 * TIDAK bisa lagi dilepas untuk dikerjakan ulang sementara ia masih berjalan.
 * Batas total per tabel menjaga agar satu jalan tidak pernah mendekati 600 detik
 * sejak awal. Dijaga tests/Unit/AntreanTest.php.
 */
class PruneOldRecordsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function handle(Pemangkas $pemangkas): void
    {
        $hasil = $pemangkas->jalankan();

        $total = array_sum($hasil);

        if ($total === 0) {
            return;
        }

        // Dicatat karena inilah satu-satunya jejak bahwa retensi benar-benar
        // berjalan. Pemangkas yang diam-diam berhenti bekerja tidak menghasilkan
        // galat apa pun — ia cuma membuat tabel tumbuh terus, dan itu baru
        // terlihat berbulan-bulan kemudian sebagai database yang membengkak.
        Log::info('Pemangkasan retensi selesai', ['total' => $total] + $hasil);
    }
}
