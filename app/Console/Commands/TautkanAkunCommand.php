<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\LinkedAccounts\LinkedAccountSync;
use Illuminate\Console\Command;

/**
 * Penautan awal: seluruh akun yang sudah ada dikirim ke aplikasi pesanvo.
 *
 * Dijalankan sekali setelah penautan dinyalakan, di kedua aplikasi. Akun yang
 * belum ada di seberang dibuatkan; akun yang sudah ada (email sama) hanya
 * ditautkan — kata sandinya TIDAK ditimpa, karena belum ada cara mengetahui
 * mana dari dua kata sandi berbeda yang masih diingat pemiliknya. Sejak
 * penggantian kata sandi berikutnya, keduanya sama.
 */
class TautkanAkunCommand extends Command
{
    protected $signature = 'akun-tertaut:tautkan {--dry-run : Hitung saja, jangan kirim apa pun}';

    protected $description = 'Tautkan seluruh akun yang sudah ada ke aplikasi pesanvo (sekali jalan)';

    public function handle(LinkedAccountSync $sync): int
    {
        if (! $sync->enabled()) {
            $this->error('Penautan akun mati: isi LINKED_ACCOUNTS_URL dan LINKED_ACCOUNTS_SECRET dulu.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->line('Akan dikirim: '.User::count().' akun.');

            return self::SUCCESS;
        }

        $tindakan = [];
        $status = [];

        User::orderBy('id')->chunkById(100, function ($users) use ($sync, &$tindakan, &$status) {
            foreach ($users as $user) {
                $hasil = $sync->push($user, LinkedAccountSync::MODE_LINK);
                $status[$hasil['status']] = ($status[$hasil['status']] ?? 0) + 1;

                if ($hasil['action']) {
                    $tindakan[$hasil['action']] = ($tindakan[$hasil['action']] ?? 0) + 1;
                }
            }
        });

        foreach ($status + $tindakan as $nama => $jumlah) {
            $this->line(str_pad($nama, 16).$jumlah);
        }

        return ($status[LinkedAccountSync::GAGAL] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
