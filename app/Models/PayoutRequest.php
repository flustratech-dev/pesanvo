<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permohonan pencairan dana komisi oleh mitra/reseller.
 */
class PayoutRequest extends Model
{
    protected $fillable = [
        'payout_number',
        'referral_code_id',
        'user_id',
        'amount',
        'fee_percent',
        'fee_amount',
        'net_amount',
        'bank_name',
        'bank_account_number',
        'bank_account_name',
        'status',
        'notes',
        'admin_notes',
        'paid_at',
        'paid_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'fee_percent' => 'integer',
            'fee_amount' => 'integer',
            'net_amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PayoutRequest $model) {
            if (empty($model->payout_number)) {
                $model->payout_number = static::buatNomor();
            }
            if (empty($model->fee_percent)) {
                $model->fee_percent = config('billing.payout_fee_percent', 5);
            }
            if (empty($model->fee_amount)) {
                $model->fee_amount = (int) round($model->amount * $model->fee_percent / 100);
            }
            if (empty($model->net_amount)) {
                $model->net_amount = max(0, $model->amount - $model->fee_amount);
            }
        });
    }

    public static function buatNomor(): string
    {
        $count = static::count() + 1;

        return 'PO-WA-'.now()->format('Ym').'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    public function formattedAmount(): string
    {
        return 'Rp '.number_format($this->amount, 0, ',', '.');
    }

    public function formattedFee(): string
    {
        return 'Rp '.number_format($this->fee_amount, 0, ',', '.');
    }

    public function formattedNetAmount(): string
    {
        return 'Rp '.number_format($this->net_amount ?: ($this->amount - $this->fee_amount), 0, ',', '.');
    }

    public function referralCode(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
