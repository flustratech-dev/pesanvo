<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu kabar untuk satu orang.
 *
 * Tidak pernah diubah setelah dibuat, kecuali `read_at`. Notifikasi yang isinya
 * bisa berubah setelah dibaca bukan riwayat — dan riwayat itulah yang membuat
 * lonceng berguna melebihi WhatsApp yang sudah digulir hilang.
 */
class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'workspace_id',
        'audience',
        'type',
        'level',
        'title',
        'body',
        'url',
        'dedupe_key',
        'read_at',
    ];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function scopeUntuk(Builder $q, User $user, string $audience): Builder
    {
        return $q->where('user_id', $user->id)->where('audience', $audience);
    }

    public function scopeBelumDibaca(Builder $q): Builder
    {
        return $q->whereNull('read_at');
    }

    public function sudahDibaca(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Warna lencana. Dipetakan di sini, bukan ditulis ulang tiap Blade —
     * aturan yang sama dengan `App\Support\StatusBadge`.
     */
    public function warna(): string
    {
        return match ($this->level) {
            'success' => 'hijau',
            'warning' => 'kuning',
            'danger' => 'merah',
            default => 'biru',
        };
    }

    /** Ikon SVG per tingkat. Satu bentuk per arti, bukan satu per jenis kabar. */
    public function ikon(): string
    {
        return match ($this->level) {
            'success' => 'M9 12l2 2 4-4M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
            'warning' => 'M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z',
            'danger' => 'M12 8v5M12 17h.01M12 22a10 10 0 1 1 0-20 10 10 0 0 1 0 20z',
            default => 'M12 16v-4M12 8h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
        };
    }
}
