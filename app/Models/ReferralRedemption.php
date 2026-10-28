<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu penukaran kode oleh satu workspace.
 *
 * Nilainya (`discount_amount`, `commission_amount`) DIBEKUKAN saat penukaran.
 * Persen di `referral_codes` boleh berubah kapan saja, dan komisi yang ikut
 * berubah setelah tagihannya lunas berarti angka yang sudah dijanjikan ke
 * reseller berubah sendiri tanpa ada yang memutuskannya.
 */
class ReferralRedemption extends Model
{
    protected $fillable = [
        'referral_code_id',
        'workspace_id',
        'invoice_id',
        'discount_amount',
        'commission_amount',
        'status',
        'approved_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'discount_amount' => 'integer',
            'commission_amount' => 'integer',
        ];
    }

    public function code(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class, 'referral_code_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
