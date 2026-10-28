<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Penyetelan yang harus bisa diubah tanpa redeploy.
 *
 * Bukan pengganti config. Yang pindah ke sini cuma yang nilainya baru diketahui
 * SETELAH aplikasi berjalan — dan contoh pertamanya adalah workspace pengirim
 * notifikasi: id-nya baru ada setelah workspace itu dibuat lewat dashboard,
 * jadi menaruhnya di env berarti deploy, buat workspace, salin id, deploy lagi.
 * Selama dua deploy itu seluruh pemberitahuan diam tanpa satu pun gejala.
 *
 * Nilai env tetap dipakai sebagai cadangan, jadi pemasangan yang sudah terlanjur
 * mengisinya tidak perlu diubah.
 */
class AppSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function ambil(string $key, mixed $bawaan = null): mixed
    {
        try {
            $nilai = Cache::rememberForever(
                "app_setting:{$key}",
                fn () => static::query()->whereKey($key)->value('value')
            );

            return blank($nilai) ? $bawaan : $nilai;
        } catch (\Throwable) {
            return $bawaan;
        }
    }

    public static function simpan(string $key, mixed $value): void
    {
        try {
            static::updateOrCreate(['key' => $key], ['value' => blank($value) ? null : (string) $value]);

            Cache::forget("app_setting:{$key}");
        } catch (\Throwable) {
            // Abaikan jika tabel belum siap
        }
    }
}
