<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\LinkedAccounts\LinkedAccountSync;
use Illuminate\Console\Command;

/**
 * Mengulang pengiriman akun yang tertunda ke aplikasi pesanvo.
 *
 * Dijadwalkan tiap menit. Ini yang membuat "satu akun di dua aplikasi" tetap
 * benar setelah aplikasi seberang mati sebentar — tanpa perintah ini, kata
 * sandi yang diganti saat seberang sedang deploy tidak pernah sampai.
 */
class KirimAkunTertautCommand extends Command
{
    protected $signature = 'akun-tertaut:kirim {--batas=200 : Jumlah akun maksimum per putaran}';

    protected $description = 'Kirim ulang perubahan akun yang belum sampai ke aplikasi pesanvo';

    public function handle(LinkedAccountSync $sync): int
    {
        if (! $sync->enabled()) {
            $this->line('Penautan akun mati (LINKED_ACCOUNTS_URL / LINKED_ACCOUNTS_SECRET kosong).');

            return self::SUCCESS;
        }

        $hasil = [LinkedAccountSync::TERKIRIM => 0, LinkedAccountSync::DITOLAK => 0, LinkedAccountSync::GAGAL => 0];

        User::whereNotNull('linked_sync_pending_at')
            ->orderBy('linked_sync_pending_at')
            ->limit((int) $this->option('batas'))
            ->get()
            ->each(function (User $user) use ($sync, &$hasil) {
                $hasil[$sync->push($user)['status']]++;
            });

        $this->line("Terkirim {$hasil['terkirim']}, ditolak {$hasil['ditolak']}, gagal {$hasil['gagal']}.");

        return self::SUCCESS;
    }
}
