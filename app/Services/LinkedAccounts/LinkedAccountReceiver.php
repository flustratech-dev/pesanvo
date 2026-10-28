<?php

namespace App\Services\LinkedAccounts;

use App\Models\PendingExemption;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Menerapkan perubahan akun yang datang dari aplikasi pesanvo — sisi masuk
 * akun tertaut.
 *
 * Seluruh penulisan lewat query builder, bukan model. Itu yang memutus
 * lingkaran: perubahan yang datang dari seberang tidak memicu observer, jadi
 * tidak dikirim balik ke asalnya lalu dikirim balik lagi ke sini. Sekaligus
 * melewati cast `hashed` — hash dari seberang disimpan apa adanya, bukan
 * ditolak karena biaya bcrypt-nya berbeda sedikit dari konfigurasi di sini.
 */
class LinkedAccountReceiver
{
    public const DIBUAT = 'dibuat';

    public const DIPERBARUI = 'diperbarui';

    public const TIDAK_BERUBAH = 'tidak_berubah';

    public const DILINDUNGI = 'dilindungi';

    /**
     * @param  array{mode: string, email: string, previous_email: ?string, name: ?string, phone: ?string, password_hash: string, email_verified_at: ?string}  $data
     *
     * @throws LinkedAccountConflict
     */
    public function apply(array $data): string
    {
        $email = mb_strtolower(trim($data['email']));
        $emailLama = filled($data['previous_email'] ?? null) ? mb_strtolower(trim($data['previous_email'])) : null;

        return DB::transaction(function () use ($data, $email, $emailLama) {
            $user = null;

            if ($emailLama !== null && $emailLama !== $email) {
                $user = User::where('email', $emailLama)->lockForUpdate()->first();
            }

            $user ??= User::where('email', $email)->lockForUpdate()->first();

            if (! $user) {
                $this->buat($data, $email);

                return self::DIBUAT;
            }

            // Super admin tidak pernah diubah dari luar. Rahasia penautan yang
            // bocor di aplikasi seberang tidak boleh berubah jadi kunci masuk
            // panel admin gateway ini; identitas admin kedua aplikasi sudah
            // disamakan lewat env, bukan lewat penautan.
            if ($user->is_super_admin) {
                return self::DILINDUNGI;
            }

            // Akun yang SUDAH ADA di sini hanya boleh diubah oleh kiriman yang
            // emailnya sudah terbukti milik pengirimnya di aplikasi asal.
            // Tanpa syarat ini, siapa pun bisa mendaftar di seberang memakai
            // email orang lain — pendaftaran belum tentu memverifikasi email —
            // lalu penautan mengganti kata sandi akun asli pemilik email itu di
            // sini. Akun baru tetap dibuat tanpa syarat ini: belum ada pemilik
            // yang bisa dirugikan.
            if (blank($data['email_verified_at'] ?? null)) {
                return self::DILINDUNGI;
            }

            $ubah = [];

            if ($user->email !== $email) {
                if (User::where('email', $email)->whereKeyNot($user->id)->exists()) {
                    throw new LinkedAccountConflict("Email {$email} sudah dipakai akun lain di gateway.");
                }

                $ubah['email'] = $email;
            }

            if ($data['mode'] === LinkedAccountSync::MODE_SYNC
                && ! hash_equals((string) $user->getRawOriginal('password'), $data['password_hash'])) {
                $ubah['password'] = $data['password_hash'];
            }

            if ($user->email_verified_at === null && filled($data['email_verified_at'] ?? null)) {
                $ubah['email_verified_at'] = Carbon::parse($data['email_verified_at']);
            }

            if ($ubah === []) {
                return self::TIDAK_BERUBAH;
            }

            DB::table('users')->where('id', $user->id)->update($ubah + ['updated_at' => now()]);

            if (isset($ubah['email'])) {
                // Kolom salinan email pemilik ikut, kalau tidak surel tagihan dan
                // pemberitahuan terus terkirim ke alamat yang sudah ditinggalkan.
                DB::table('workspaces')->where('owner_id', $user->id)->update(['owner_email' => $email]);
            }

            return self::DIPERBARUI;
        });
    }

    private function buat(array $data, string $email): void
    {
        $id = DB::table('users')->insertGetId([
            'name' => Str::limit(trim((string) ($data['name'] ?? '')) ?: Str::before($email, '@'), 80, ''),
            'email' => $email,
            'phone' => PhoneNumber::normalize($data['phone'] ?? null),
            'password' => $data['password_hash'],
            'email_verified_at' => filled($data['email_verified_at'] ?? null) ? Carbon::parse($data['email_verified_at']) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Pembebasan yang disiapkan admin untuk email ini berlaku sama seperti
        // saat orangnya mendaftar langsung di sini. Dijalankan tanpa observer
        // supaya penandaan bebas tidak dikirim balik sebagai perubahan akun.
        User::withoutEvents(fn () => PendingExemption::terapkanUntuk(User::findOrFail($id)));
    }
}
