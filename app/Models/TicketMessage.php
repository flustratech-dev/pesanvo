<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu balasan di dalam tiket.
 *
 * `is_from_admin` adalah penanda yang menentukan tampilan, bukan `user_id`:
 * `user_id` yang kosong juga terjadi saat akun penanyanya sudah dihapus, dan
 * balasan pelanggan yang salah terbaca sebagai balasan kami membuat riwayat
 * percakapan tidak bisa dipercaya.
 *
 * Nama admin sengaja tidak pernah ditampilkan ke pelanggan — yang menjawab
 * adalah Pesanvo, bukan orang tertentu.
 */
class TicketMessage extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'body',
        'is_from_admin',
        'attachment_path',
        'attachment_name',
    ];

    protected function casts(): array
    {
        return ['is_from_admin' => 'boolean'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function punyaLampiran(): bool
    {
        return filled($this->attachment_path);
    }
}
