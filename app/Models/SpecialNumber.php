<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * Nomor milik perusahaan sendiri, dikecualikan dari batas paket.
 *
 * Dua akibatnya, dan keduanya sengaja terbatas:
 *
 *  - Tidak menghitung kuota `max_sessions` workspace mana pun, jadi nomor ini
 *    bisa ditautkan di workspace mana saja tanpa memakan jatah pelanggan.
 *  - Tidak ikut dilepas saat langganan mati, dan pengiriman darinya tidak
 *    dihalangi status workspace.
 *
 * Yang TIDAK dilakukannya: ia tidak membebaskan seluruh workspace. Workspace
 * yang menampung nomor istimewa tetap ditagih untuk nomor-nomornya yang lain.
 * Pembebasan menyeluruh punya jalurnya sendiri — `users.is_exempt` — supaya
 * "nomor kami menumpang di sini" tidak diam-diam berubah menjadi "workspace ini
 * gratis selamanya".
 */
class SpecialNumber extends Model
{
    protected $fillable = ['phone', 'email', 'label', 'note', 'created_by'];

    /**
     * Daftar nomor dalam bentuk ternormalisasi.
     *
     * Di-cache karena dibaca di jalur terpanas sistem ini — tiap pesan keluar
     * dan tiap pemeriksaan sesi. Tabelnya hampir tidak pernah berubah, jadi
     * membaca ulang dari database tiap kali adalah kueri yang jawabannya sudah
     * pasti sama. Cache-nya dibuang tiap kali barisnya berubah, di bawah.
     *
     * @return array<int, string>
     */
    public static function daftar(): array
    {
        return Cache::rememberForever('nomor_istimewa', fn () => static::query()->pluck('phone')->all());
    }

    public static function cocok(?string $phone): bool
    {
        if (blank($phone)) {
            return false;
        }

        return in_array(PhoneNumber::normalize($phone) ?? $phone, static::daftar(), true);
    }

    protected static function booted(): void
    {
        $buang = fn () => Cache::forget('nomor_istimewa');

        static::saved($buang);
        static::deleted($buang);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
