<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'city',
        'address',
        'bio',
        'google_id',
        'avatar',
        'email_verified_at',
        'password',
        'is_super_admin',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',

        // Rahasia TOTP dan kode pemulihan setara kunci masuk kedua. Keduanya
        // wajib di sini supaya tidak pernah ikut terbawa saat model ini
        // di-serialize ke JSON, log, atau payload job.
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_super_admin' => 'boolean',
            'is_exempt' => 'boolean',
            'password' => 'hashed',
            'deletion_requested_at' => 'datetime',
            'deletion_scheduled_for' => 'datetime',

            // `encrypted`, BUKAN `hashed`: rahasia TOTP harus bisa dibaca
            // kembali apa adanya untuk menghitung kode tiap 30 detik.
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** 2FA benar-benar menyala, bukan sekadar rahasianya sudah dibuat. */
    public function duaFaktorAktif(): bool
    {
        return $this->two_factor_confirmed_at !== null && filled($this->two_factor_secret);
    }

    /**
     * Akun ini WAJIB memakai 2FA.
     *
     * Dikendalikan oleh konfigurasi `auth.two_factor_mandatory_for_admin`.
     * Bawaannya false (opsional untuk seluruh peran termasuk super admin).
     * Jika diatur true, super admin diwajibkan memasangnya sebelum mengakses
     * dashboard maupun panel admin.
     */
    public function wajibDuaFaktor(): bool
    {
        return (bool) config('auth.two_factor_mandatory_for_admin', false) && (bool) $this->is_super_admin;
    }

    public function guideProgress(): HasMany
    {
        return $this->hasMany(UserGuideProgress::class);
    }

    /**
     * Sudah pernah melihat sebuah tur pengenalan.
     *
     * `$version` ada supaya tur yang isinya berubah besar bisa ditampilkan ulang
     * kepada yang sudah pernah melihatnya — naikkan versinya di tempat tur itu
     * dipasang, bukan hapus barisnya.
     */
    public function hasSeenGuide(string $guideKey, int $version = 1): bool
    {
        return $this->guideProgress()
            ->where('guide_key', $guideKey)
            ->where('guide_version', '>=', $version)
            ->exists();
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function roleIn(Workspace $workspace): ?string
    {
        return $this->workspaces->firstWhere('id', $workspace->id)?->pivot->role;
    }

    /**
     * Boleh mengubah pengaturan workspace: menambah anggota, mengelola sesi,
     * membuat dan mencabut API key. Member biasa hanya boleh melihat dan
     * mengirim pesan.
     */
    public function canManage(Workspace $workspace): bool
    {
        return $this->is_super_admin || in_array($this->roleIn($workspace), ['owner', 'admin'], true);
    }

    public function avatarUrl(): ?string
    {
        if (! $this->avatar) {
            return null;
        }

        return str_starts_with($this->avatar, 'http')
            ? $this->avatar
            : asset('storage/'.$this->avatar);
    }

    public function referralCode(): HasOne
    {
        return $this->hasOne(ReferralCode::class, 'owner_user_id');
    }

    public function payoutRequests(): HasMany
    {
        return $this->hasMany(PayoutRequest::class);
    }
}
