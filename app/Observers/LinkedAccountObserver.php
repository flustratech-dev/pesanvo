<?php

namespace App\Observers;

use App\Jobs\PushLinkedAccountJob;
use App\Models\User;
use App\Services\LinkedAccounts\LinkedAccountSync;

/**
 * Menangkap setiap perubahan identitas masuk, dari jalur mana pun.
 *
 * Dipasang di model, bukan di controller pendaftaran: akun lahir dan kata
 * sandi berganti lewat banyak jalan — daftar, daftar lewat Google, ganti kata
 * sandi, lupa kata sandi, atur ulang oleh admin, rehash otomatis saat masuk.
 * Penautan yang dipasang per controller adalah penautan yang suatu saat
 * terlewat di satu jalan, dan yang terlewat berarti dua aplikasi dengan kata
 * sandi berbeda untuk orang yang sama.
 *
 * Penghapusan akun sengaja TIDAK diteruskan: menghapus akun gateway tidak boleh
 * ikut menghapus akun VPS orang itu di aplikasi seberang.
 */
class LinkedAccountObserver
{
    public function created(User $user): void
    {
        $this->antrekan($user, null);
    }

    public function updated(User $user): void
    {
        // Verifikasi email ikut dikirim: sejak saat itulah seberang mau menerima
        // perubahan kata sandi untuk akun yang sudah ada di sana.
        if (! $user->wasChanged(['password', 'email', 'email_verified_at'])) {
            return;
        }

        $this->antrekan($user, $user->wasChanged('email') ? $user->getOriginal('email') : null);
    }

    private function antrekan(User $user, ?string $emailLama): void
    {
        // Ditandai walau penautannya sedang mati: begitu env-nya diisi,
        // perubahan selama masa mati ikut terkirim, bukan hilang.
        LinkedAccountSync::markPending($user, $emailLama);

        if (app(LinkedAccountSync::class)->enabled()) {
            PushLinkedAccountJob::dispatch($user->id)->afterCommit();
        }
    }
}
