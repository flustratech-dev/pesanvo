<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu permintaan paket yang ditolak karena kapasitas platform penuh.
 *
 * Alasan tabel ini ada tertulis di migrasinya. Yang penting saat membacanya di
 * sini: barisnya tidak pernah dihapus setelah dilayani, dan `converted_at`
 * adalah yang menjawab "berapa lama orang benar-benar menunggu".
 */
class WaitlistEntry extends Model
{
    protected $fillable = [
        'workspace_id',
        'plan_slug',
        'period',
        'slots',
        'notified_at',
        'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'slots' => 'integer',
            'notified_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Yang masih benar-benar menunggu: belum dikabari, dan belum jadi membayar.
     *
     * Urut dari yang paling lama menunggu. Antrean kapasitas yang tidak berurut
     * waktu adalah antrean yang orangnya tidak percaya lagi.
     */
    public function scopeMenunggu($query)
    {
        return $query->whereNull('notified_at')
            ->whereNull('converted_at')
            ->orderBy('created_at');
    }
}
