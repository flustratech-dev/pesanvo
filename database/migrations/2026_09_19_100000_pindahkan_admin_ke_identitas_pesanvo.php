<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Admin yang sudah ada dipindahkan ke identitas admin Pesanvo — diubah di
 * tempat, BUKAN dibuatkan akun baru.
 *
 * Alasannya ada di dua arah. Seeder mencari admin berdasarkan email; begitu
 * `ADMIN_EMAIL` diganti ke email Pesanvo, seeder tidak lagi menemukan admin
 * lama dan membuat admin kedua — panel admin berakhir dengan dua pemilik, dan
 * yang lama tetap bisa masuk dengan identitas merek yang sudah ditinggalkan.
 * Sebaliknya, workspace, API key, dan nomor tertaut milik admin lama harus
 * tetap di tangan orang yang sama; memindahkan kepemilikannya satu per satu ke
 * akun baru adalah pekerjaan yang pasti ada yang terlewat.
 *
 * Yang dipindahkan adalah super admin tertua — akun pertama yang dibuat seeder.
 * Tidak berbuat apa pun kalau email baru sudah ada (migrasi ini atau seeder
 * sudah pernah menanganinya) atau kalau belum ada super admin sama sekali
 * (database baru: seeder yang membuatkan akunnya).
 *
 * Kata sandi hanya diganti kalau `ADMIN_PASSWORD` diisi — kata sandi bawaan
 * pengembangan tidak pernah ditulis ke akun yang sudah hidup. 2FA admin tidak
 * disentuh: rahasianya tidak bergantung pada email.
 */
return new class extends Migration
{
    public function up(): void
    {
        $admin = config('pesanvo.admin');
        $email = $admin['email'] ?? null;

        if (blank($email) || DB::table('users')->where('email', $email)->exists()) {
            return;
        }

        $lama = DB::table('users')->where('is_super_admin', true)->orderBy('id')->first();

        if (! $lama) {
            return;
        }

        $ubah = [
            'name' => $admin['name'],
            'email' => $email,
            'phone' => $admin['phone'],
            'company' => $admin['company'],
            // Email baru milik tim sendiri; akun ini sudah terverifikasi sebelumnya.
            'email_verified_at' => $lama->email_verified_at ?? now(),
            'updated_at' => now(),
        ];

        if (filled($admin['password'])) {
            $ubah['password'] = Hash::make($admin['password']);
        }

        DB::transaction(function () use ($lama, $ubah, $email) {
            DB::table('users')->where('id', $lama->id)->update($ubah);

            // Jejaknya ditulis ke catatan audit, karena email lama tidak disimpan
            // di tempat lain setelah baris ini berubah.
            DB::table('audit_logs')->insert([
                'user_id' => $lama->id,
                'action' => 'admin.identitas_dipindahkan',
                'subject_type' => User::class,
                'subject_id' => (string) $lama->id,
                'context' => json_encode([
                    'email_lama' => $lama->email,
                    'email_baru' => $email,
                    'kata_sandi_diganti' => array_key_exists('password', $ubah),
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Tidak dibalik. Identitas lama sudah ditinggalkan, dan membalik migrasi ini
     * berarti mengembalikan email merek lama ke akun admin.
     */
    public function down(): void {}
};
