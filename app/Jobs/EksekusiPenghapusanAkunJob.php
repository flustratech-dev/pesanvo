<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\PenghapusanAkun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Menghapus akun yang masa tunggunya sudah lewat.
 *
 * Berjalan harian, bukan dijadwalkan per akun pada tanggalnya. Job tertunda
 * yang dijadwalkan berbulan ke depan akan hilang bersama antrean setiap kali
 * Redis dibersihkan atau tabel `jobs` dipangkas — dan akun yang sudah dijanjikan
 * terhapus akan hidup selamanya tanpa satu pun gejala. Menyapu tabelnya tiap
 * hari membuat keadaan yang benar terbaca dari data, bukan dari pekerjaan yang
 * mengambang.
 */
class EksekusiPenghapusanAkunJob implements ShouldQueue
{
    use Queueable;

    public function handle(PenghapusanAkun $penghapusan): void
    {
        $jatuhTempo = User::whereNotNull('deletion_scheduled_for')
            ->where('deletion_scheduled_for', '<=', now())
            ->get();

        foreach ($jatuhTempo as $user) {
            try {
                $penghapusan->jalankan($user, 'masa tunggu penghapusan akun habis');
            } catch (\Throwable $e) {
                // Satu akun yang gagal tidak boleh menghentikan sisanya.
                // Kegagalannya berulang tiap hari sampai ditangani, dan itu
                // memang yang diinginkan: yang menunggu dihapus tidak boleh
                // diam-diam berhenti mengantre.
                Log::error('Gagal menghapus akun terjadwal: '.$e->getMessage(), ['user' => $user->id]);
            }
        }
    }
}
