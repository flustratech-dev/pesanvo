<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataExport extends Model
{
    protected $fillable = ['workspace_id', 'requested_by', 'status', 'path', 'size', 'error', 'expires_at', 'finished_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'finished_at' => 'datetime',
            'size' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function pemohon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Berkasnya masih bisa diunduh.
     *
     * Kedaluwarsa diperiksa DI SINI dan bukan hanya saat pemangkasan: job
     * pemangkas berjalan sekali sehari, dan tanpa pemeriksaan ini berkas yang
     * sudah lewat masanya tetap terunduh sepanjang sisa hari itu oleh siapa pun
     * yang masih memegang tautannya.
     */
    public function bisaDiunduh(): bool
    {
        return $this->status === 'siap'
            && filled($this->path)
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    public function ukuranTerbaca(): string
    {
        if (! $this->size) {
            return '—';
        }

        return $this->size >= 1_048_576
            ? number_format($this->size / 1_048_576, 1, ',', '.').' MB'
            : number_format($this->size / 1024, 0, ',', '.').' KB';
    }
}
