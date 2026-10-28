<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Akun super admin — dibuat DAN disamakan dengan env `ADMIN_*` setiap deploy.
 *
 * Ada karena panel admin hanya bisa dibuka oleh super admin, dan super admin
 * hanya bisa diangkat oleh super admin lain — tanpa akun pertama yang lahir di
 * luar antarmuka, panel itu terkunci dari dalam sejak menit pertama.
 *
 * `start.sh` menjalankannya setiap boot, bukan menunggu seseorang ingat
 * menjalankan `db:seed`. Itu sudah terjadi: produksi dimulai dari database
 * kosong, admin tidak pernah terbentuk, dan pemilik akunnya disuruh mendaftar
 * ulang padahal email dan kata sandinya benar.
 *
 * Env adalah sumber kebenaran identitas admin, persis seperti seeder aplikasi
 * pesanvo yang menulis ulang admin-nya setiap seed: nama, nomor, perusahaan,
 * dan kata sandi disamakan dengan env tiap kali berjalan. Konsekuensinya —
 * dan ini disengaja — kata sandi admin diganti lewat env, bukan lewat halaman
 * profil; yang diganti lewat profil kembali ke nilai env pada deploy berikutnya.
 * Kalau tidak begitu, admin kedua aplikasi yang katanya "sama persis" diam-diam
 * punya dua kata sandi berbeda begitu env diganti.
 *
 * Di produksi admin TIDAK dibuat dan kata sandinya TIDAK disentuh selama
 * `ADMIN_PASSWORD` kosong: kata sandi bawaan `12345678` tertulis di repo ini,
 * artinya sudah bocor, dan tidak boleh sampai menjaga panel admin sungguhan.
 */
class AdminUserSeeder extends Seeder
{
    private const SANDI_BAWAAN = '12345678';

    public function run(): void
    {
        $admin = config('pesanvo.admin');
        $email = mb_strtolower((string) $admin['email']);
        $sandiEnv = filled($admin['password']) ? (string) $admin['password'] : null;

        if ($sandiEnv === null && app()->isProduction()) {
            $this->command?->warn('ADMIN_PASSWORD kosong di produksi: akun admin tidak dibuat atau diubah.');
        }

        $sandi = $sandiEnv ?? (app()->isProduction() ? null : self::SANDI_BAWAAN);

        $user = User::where('email', $email)->first();

        if (! $user) {
            if ($sandi === null) {
                return;
            }

            User::create([
                'name' => $admin['name'],
                'email' => $email,
                'phone' => $admin['phone'],
                'company' => $admin['company'],
                'password' => Hash::make($sandi),
                'email_verified_at' => now(),
                'is_super_admin' => true,
            ]);

            $this->command?->info("Super admin dibuat: {$email}");
            $this->peringatkanSandiBawaan($sandi);

            return;
        }

        // Akun dengan email admin yang sudah ada — dari seeder sebelumnya, dari
        // pendaftaran biasa, atau dari akun tertaut — diangkat dan disamakan.
        $user->forceFill([
            'name' => $admin['name'],
            'phone' => $admin['phone'],
            'company' => $admin['company'],
            'is_super_admin' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);

        // Hash::check dulu: menulis hash baru tiap boot untuk kata sandi yang
        // sama tidak mengubah apa pun selain memicu pengiriman akun tertaut
        // tanpa alasan.
        if ($sandiEnv !== null && ! Hash::check($sandiEnv, $user->password)) {
            $user->password = Hash::make($sandiEnv);
            $this->command?->info("Kata sandi admin disamakan dengan env: {$email}");
        }

        if ($user->isDirty()) {
            $user->save();
            $this->command?->info("Super admin disamakan dengan env: {$email}");
        }

        if ($sandiEnv !== null) {
            $this->peringatkanSandiBawaan($sandiEnv);
        }
    }

    private function peringatkanSandiBawaan(string $sandi): void
    {
        if ($sandi === self::SANDI_BAWAAN) {
            $this->command?->warn(
                'Kata sandi bawaan masih dipakai. Isi ADMIN_PASSWORD di env sebelum menyalakan produksi.'
            );
        }
    }
}
