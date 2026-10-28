<?php

namespace App\Jobs;

use App\Models\WaitlistEntry;
use App\Services\Notifications\EmailNotifier;
use App\Services\Notifications\Notifier;
use App\Support\KapasitasPlatform;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Mengabari daftar tunggu begitu kapasitas benar-benar tersedia.
 *
 * Halaman penolakan menjanjikan "kami mengabari Anda begitu slotnya tersedia,
 * tanpa perlu menekan apa pun lagi". Job inilah seluruh isi janji itu. Tanpa
 * ia, daftar tunggu cuma tabel yang tidak pernah dibaca siapa pun, dan
 * kalimatnya kembali jadi sopan-tapi-bohong — persis yang sedang diperbaiki.
 *
 * Dijalankan tiap jam, bukan tiap menit: kapasitas bebas ketika langganan
 * berakhir atau workspace dihentikan, dan keduanya peristiwa harian. Memeriksa
 * tiap menit cuma menambah beban pada server yang RAM-nya justru sedang
 * diperebutkan.
 *
 * Urut dari yang paling lama menunggu, dan hanya sebanyak slot yang benar-benar
 * ada. Mengabari sepuluh orang untuk satu slot berarti sembilan orang menekan
 * tombol lalu ditolak lagi — dan yang kedua itu jauh lebih merusak daripada
 * penolakan pertama.
 */
class KabariDaftarTungguJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public function handle(Notifier $notifikasi, EmailNotifier $email): void
    {
        $tersisa = KapasitasPlatform::tersisa();

        if ($tersisa <= 0) {
            return;
        }

        foreach (WaitlistEntry::menunggu()->with('workspace')->get() as $antrean) {
            // Elite butuh dua slot. Mengabari peminatnya saat baru satu slot
            // bebas berarti menjanjikan sesuatu yang tetap akan gagal di
            // checkout — dan gagal untuk kedua kalinya, setelah dijanjikan.
            if ($antrean->slots > $tersisa) {
                continue;
            }

            if (! $antrean->workspace) {
                continue;
            }

            $paket = config("plans.catalog.{$antrean->plan_slug}.name", $antrean->plan_slug);

            $judul = 'Kapasitas tersedia — paket '.$paket.' bisa diambil sekarang';
            $isi = 'Slot nomor WhatsApp sudah tersedia lagi. Anda bisa melanjutkan '
                .'pengambilan paket '.$paket.' dari halaman Langganan. '
                .'Kami menahan pemberitahuan ini sampai kapasitasnya benar-benar ada, '
                .'jadi tidak akan ditolak lagi seperti kemarin.';

            /*
             | Notifikasi dulu, email sesudahnya, dan `notified_at` ditulis
             | apa pun hasilnya.
             |
             | Kalau penulisannya digantungkan pada email yang berhasil, SMTP
             | yang sedang bermasalah membuat job ini mengabari orang yang sama
             | tiap jam. Lonceng di dalam aplikasi tidak bergantung pada apa pun
             | di luar, jadi ia yang jadi patokan bahwa kabarnya sampai.
            */
            $notifikasi->keWorkspace(
                workspace: $antrean->workspace,
                type: 'kapasitas.tersedia',
                title: $judul,
                body: $isi,
                url: route('billing.plans'),
                level: 'success',
                dedupe: 'waitlist:'.$antrean->id,
            );

            $email->kabarWorkspace($antrean->workspace, $judul, $isi, route('billing.plans'));

            $antrean->forceFill(['notified_at' => now()])->save();

            $tersisa -= $antrean->slots;

            Log::info('Daftar tunggu dikabari', [
                'workspace_id' => $antrean->workspace_id,
                'plan' => $antrean->plan_slug,
                'menunggu_hari' => $antrean->created_at->diffInDays(now()),
            ]);

            if ($tersisa <= 0) {
                return;
            }
        }
    }
}
