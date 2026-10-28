<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Pembebasan yang menunggu pemiliknya mendaftar.
 *
 * Tanpa ini, membebaskan calon pelanggan berarti menunggu mereka mendaftar
 * lebih dulu lalu mengingat untuk kembali menandainya — dan yang lupa ditandai
 * akan tertagih seperti pelanggan biasa, lalu mengeluh tentang janji yang
 * sudah diberikan seseorang.
 *
 * Barisnya tidak pernah dihapus saat dipakai, hanya ditandai `claimed_at`.
 * Tanpa jejaknya tidak ada cara menjawab "siapa yang pernah membebaskan akun
 * ini, dan kapan" — pertanyaan yang selalu muncul saat ada yang bertanya
 * kenapa sebuah akun tidak pernah ditagih.
 */
class PendingExemption extends Model
{
    protected $fillable = [
        'email',
        'note',
        'created_by',
        'claimed_at',
        'claimed_by_user_id',
    ];

    protected function casts(): array
    {
        return ['claimed_at' => 'datetime'];
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function penerima(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by_user_id');
    }

    public function sudahDipakai(): bool
    {
        return $this->claimed_at !== null;
    }

    /** Alamat selalu disimpan dan dicocokkan dalam huruf kecil. */
    public static function normalkan(string $email): string
    {
        return Str::lower(trim($email));
    }

    /**
     * Menerapkan pembebasan yang menunggu untuk seorang pengguna yang baru saja
     * mendaftar. Aman dipanggil berkali-kali.
     */
    public static function terapkanUntuk(User $user): bool
    {
        $baris = self::where('email', self::normalkan($user->email))
            ->whereNull('claimed_at')
            ->first();

        if (! $baris) {
            return false;
        }

        $user->forceFill(['is_exempt' => true])->save();

        $baris->forceFill([
            'claimed_at' => now(),
            'claimed_by_user_id' => $user->id,
        ])->save();

        return true;
    }
}
