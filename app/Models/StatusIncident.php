<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StatusIncident extends Model
{
    protected $fillable = [
        'title', 'component', 'status', 'kind', 'impact',
        'summary', 'started_at', 'resolved_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function updates(): HasMany
    {
        return $this->hasMany(StatusIncidentUpdate::class);
    }

    public function selesai(): bool
    {
        return $this->resolved_at !== null;
    }

    /** Insiden yang masih berjalan, dipakai spanduk di halaman status. */
    public function scopeBerjalan($query)
    {
        return $query->whereNull('resolved_at');
    }

    public function label(): string
    {
        return match ($this->status) {
            'menyelidiki' => 'Sedang diselidiki',
            'teridentifikasi' => 'Penyebab ditemukan',
            'memantau' => 'Perbaikan dipasang, sedang dipantau',
            'selesai' => 'Selesai',
            default => $this->status,
        };
    }

    public function warna(): string
    {
        if ($this->kind === 'pemeliharaan') {
            return 'biru';
        }

        return match ($this->impact) {
            'total' => 'merah',
            default => 'amber',
        };
    }
}
