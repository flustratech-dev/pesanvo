<?php

namespace App\Jobs;

use App\Support\StatusLayanan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Merekam keadaan tiap komponen, lalu mengirim denyut ke pemantau luar.
 *
 * Berjalan tiap menit. Dua pekerjaan digabung di satu job dengan sengaja:
 * denyut hanya boleh dikirim kalau pemeriksaannya benar-benar berhasil
 * dijalankan. Denyut yang dikirim dari penjadwal terpisah akan terus berbunyi
 * "hidup" selama prosesnya masih ada — termasuk saat basis datanya sudah tidak
 * bisa dihubungi dan tidak ada satu pun pesan yang bisa dikirim.
 *
 * Yang dijaga job ini: **tidak pernah melempar galat.** Ia berjalan tiap menit
 * selamanya; job pemantauan yang gagal berulang justru mengisi `failed_jobs`
 * dengan barisnya sendiri, lalu memicu peringatan tentang job gagal — sistem
 * pemantauan yang menjadi sumber gangguannya sendiri.
 */
class RekamStatusJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $komponen = null;

        try {
            $komponen = StatusLayanan::periksa();
            StatusLayanan::catat($komponen);
        } catch (\Throwable $e) {
            Log::warning('Gagal merekam status layanan: '.$e->getMessage());
        }

        /*
         | Denyut hanya dikirim kalau pemeriksaan berhasil DAN tidak ada
         | komponen yang mati.
         |
         | Inilah yang membuat denyut ini berguna. Kalau ia dikirim tanpa syarat,
         | yang dibuktikannya cuma "proses PHP masih bisa berjalan" — dan itu
         | tetap benar saat engine WhatsApp mati, antrean macet, dan tidak satu
         | pun pesan pelanggan bisa keluar. Pemantau di ujung sana akan
         | menyalakan lampu hijau selama gangguan yang paling parah.
         |
         | Dengan syarat ini, denyut yang berhenti berarti salah satu dari dua
         | hal, dan keduanya memang menuntut orang bangun: aplikasinya mati, atau
         | sebuah komponen intinya mati.
        */
        $alamat = config('monitoring.heartbeat.url');

        if (blank($alamat) || $komponen === null) {
            return;
        }

        if (StatusLayanan::ringkas($komponen) === StatusLayanan::MATI) {
            return;
        }

        try {
            Http::timeout((int) config('monitoring.heartbeat.timeout'))->get($alamat);
        } catch (\Throwable $e) {
            // Pemantau yang tidak terjangkau bukan gangguan pada layanan kami,
            // dan tidak boleh menjadikannya satu.
            Log::info('Denyut ke pemantau luar gagal: '.$e->getMessage());
        }
    }
}
