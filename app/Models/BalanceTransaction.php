<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu mutasi saldo. Buku besar, bukan log.
 *
 * Bedanya penting: log boleh hilang sebagian tanpa akibat, buku besar tidak.
 * Jumlah seluruh `amount` milik sebuah workspace HARUS sama dengan
 * `workspaces.balance` — kalau tidak, ada uang pelanggan yang tidak bisa
 * dipertanggungjawabkan, dan yang menemukannya akan jadi pelanggan yang merasa
 * saldonya berkurang sendiri.
 *
 * Tidak pernah diubah setelah ditulis, dan tidak pernah dihapus. Koreksi
 * ditulis sebagai baris baru bertipe `adjustment` — riwayat yang bisa disunting
 * bukan riwayat.
 */
class BalanceTransaction extends Model
{
    protected $fillable = [
        'workspace_id',
        'type',
        'amount',
        'balance_after',
        'message_id',
        'invoice_id',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** Label Indonesia untuk tiap jenis mutasi. */
    public function labelJenis(): string
    {
        return match ($this->type) {
            'topup' => 'Isi saldo',
            'charge' => 'Pemakaian',
            'refund' => 'Pengembalian',
            'adjustment' => 'Penyesuaian',
            default => $this->type,
        };
    }
}
