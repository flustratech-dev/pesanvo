<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu permintaan penawaran Enterprise.
 *
 * Tetap disimpan walau tidak pernah menjadi pelanggan: yang paling berguna dari
 * tabel ini bukan yang berhasil, melainkan yang tidak — daftar orang yang
 * pernah butuh lebih dari Elite dan tidak jadi membeli adalah satu-satunya
 * jawaban atas "kenapa mereka pergi".
 */
class EnterpriseLead extends Model
{
    protected $fillable = [
        'user_id',
        'workspace_id',
        'name',
        'company',
        'email',
        'phone',
        'estimated_sessions',
        'estimated_messages',
        'want_retention_months',
        'want_api_rate',
        'want_onboarding',
        'estimate_monthly',
        'estimate_yearly',
        'estimate_once',
        'needs',
        'status',
        'admin_note',
        'handled_by',
        'handled_at',
    ];

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
            'estimated_sessions' => 'integer',
            'estimated_messages' => 'integer',
            'want_retention_months' => 'integer',
            'want_api_rate' => 'integer',
            'want_onboarding' => 'boolean',
            'estimate_monthly' => 'integer',
            'estimate_yearly' => 'integer',
            'estimate_once' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function penangan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(EnterprisePlan::class, 'lead_id');
    }

    /** Bola ada di kami. Inilah yang disaring "perlu dijawab" di panel admin. */
    public function perluDijawab(): bool
    {
        return $this->status === 'baru';
    }

    public function labelStatus(): string
    {
        return match ($this->status) {
            'baru' => 'Baru',
            'diproses' => 'Sedang diproses',
            'selesai' => 'Selesai',
            'ditolak' => 'Ditolak',
            default => $this->status,
        };
    }

    public function warnaStatus(): string
    {
        return match ($this->status) {
            'baru' => 'kuning',
            'diproses' => 'biru',
            'selesai' => 'hijau',
            default => 'netral',
        };
    }
}
