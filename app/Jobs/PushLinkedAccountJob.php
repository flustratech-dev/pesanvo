<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\LinkedAccounts\LinkedAccountSync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Percobaan pertama mengirim perubahan akun ke aplikasi pesanvo.
 *
 * Hanya membawa id pengguna, bukan hash kata sandinya: payload disusun saat
 * job berjalan, jadi hash tidak pernah mengendap di tabel `jobs`, dan yang
 * terkirim selalu keadaan terbaru. Satu percobaan saja — yang gagal tetap
 * bertanda tertunda dan diulang `akun-tertaut:kirim` tiap menit.
 */
class PushLinkedAccountJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public readonly int $userId) {}

    public function handle(LinkedAccountSync $sync): void
    {
        $user = User::find($this->userId);

        if (! $user || $user->linked_sync_pending_at === null) {
            return;
        }

        $sync->push($user);
    }
}
