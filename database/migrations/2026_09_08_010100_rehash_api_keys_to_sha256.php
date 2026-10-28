<?php

use App\Models\ApiKey;
use Illuminate\Database\Migrations\Migration;

/**
 * Mengisi hash cepat untuk API key yang sudah ada.
 *
 * Alasan perpindahannya di `ApiKey::hashSecret()`; alasan bentuk kolomnya di
 * migrasi `add_key_hash_fast_to_api_keys`. Yang ini soal WAKTU-nya:
 * `verifySecret()` sudah mengisi kolom itu sendiri saat kunci pertama dipakai,
 * jadi migrasi ini sebenarnya tidak wajib. Yang dibelinya adalah kunci yang
 * JARANG dipakai — integrasi yang menembak sekali sehari akan membayar 276 ms
 * pada tiap panggilan pertamanya sampai berbulan-bulan ke depan.
 *
 * Hanya kunci yang punya `key_ciphertext` yang bisa diisi di sini: rahasia
 * aslinya memang tidak tersimpan di tempat lain, dan bcrypt tidak bisa dibalik.
 * Kunci lama tanpa ciphertext tetap dilayani jalur bcrypt dan naik sendiri saat
 * dipakai — tidak satu pun berhenti bekerja.
 *
 * `key_hash` TIDAK disentuh. Itu yang membuat migrasi ini sepenuhnya reversibel:
 * `down()` cukup mengosongkan kolom baru, dan tidak ada satu pun kunci yang
 * kehilangan cara verifikasinya.
 *
 * Kegagalan dekripsi sengaja dilewati tanpa menjatuhkan migrasi. APP_KEY yang
 * pernah berputar membuat sebagian ciphertext tidak terbaca, dan container yang
 * menolak menyala karena satu baris rusak jauh lebih mahal daripada satu kunci
 * yang tetap memakai jalur lambat sampai pertama kali dipakai.
 */
return new class extends Migration
{
    public function up(): void
    {
        ApiKey::query()
            ->whereNotNull('key_ciphertext')
            ->whereNull('key_hash_fast')
            ->chunkById(100, function ($keys): void {
                foreach ($keys as $key) {
                    try {
                        $polos = $key->key_ciphertext;
                    } catch (Throwable) {
                        continue;
                    }

                    if (! is_string($polos) || ! str_contains($polos, '.')) {
                        continue;
                    }

                    [, $rahasia] = explode('.', $polos, 2);

                    $key->forceFill(['key_hash_fast' => ApiKey::hashSecret($rahasia)])->saveQuietly();
                }
            });
    }

    /**
     * Reversibel sepenuhnya: yang diisi hanya kolom tambahan, dan `key_hash`
     * yang menjadi satu-satunya sumber verifikasi kode lama tidak pernah
     * berubah.
     */
    public function down(): void
    {
        ApiKey::query()->whereNotNull('key_hash_fast')->update(['key_hash_fast' => null]);
    }
};
