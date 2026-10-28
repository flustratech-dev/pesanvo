<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu tiket bantuan.
 *
 * Tiga status, dan pembagiannya menentukan saringan "perlu dijawab" di panel:
 * `open` berarti bola ada di kami, `answered` berarti bola ada di pelanggan,
 * `closed` berarti selesai. Balasan pelanggan mengembalikannya ke `open` —
 * tanpa itu, tiket yang sudah dijawab lalu dibalas lagi menghilang dari
 * saringan admin dan tidak ada yang tahu masih ada yang menunggu.
 */
class Ticket extends Model
{
    protected $fillable = [
        'workspace_id',
        'user_id',
        'subject',
        'category',
        'priority',
        'status',
        'last_reply_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_reply_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('id');
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /** Bola ada di kami. Inilah yang disaring "perlu dijawab" di panel admin. */
    public function perluDijawab(): bool
    {
        return $this->status === 'open';
    }

    public function labelStatus(): string
    {
        return match ($this->status) {
            'open' => 'Menunggu jawaban',
            'answered' => 'Sudah dijawab',
            'closed' => 'Selesai',
            default => $this->status,
        };
    }

    public function warnaStatus(): string
    {
        return match ($this->status) {
            'open' => 'kuning',
            'answered' => 'hijau',
            default => 'netral',
        };
    }

    public function labelPrioritas(): string
    {
        return match ($this->priority) {
            'low' => 'Rendah',
            'high' => 'Tinggi',
            default => 'Normal',
        };
    }

    /** Kategori yang bisa dipilih pelanggan, dipakai form dan validasi sekaligus. */
    public static function kategori(): array
    {
        return [
            'teknis' => 'Masalah teknis',
            'tagihan' => 'Tagihan & pembayaran',
            'nomor' => 'Nomor WhatsApp / sesi',
            'api' => 'API & integrasi',
            'lainnya' => 'Lainnya',
        ];
    }

    public function labelKategori(): string
    {
        return self::kategori()[$this->category] ?? $this->category;
    }
}
