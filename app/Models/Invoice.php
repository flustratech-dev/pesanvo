<?php

namespace App\Models;

use App\Support\Plan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'number',
        'external_id',
        'workspace_id',
        'subscription_id',
        'plan_slug',
        'period',
        'amount',
        'tax_amount',
        'discount_amount',
        'intro_discount_amount',
        'referral_code_id',
        'unique_code',
        'total',
        'status',
        'channel',
        'due_at',
        'paid_at',
        'proof_path',
        'payment_confirmed_at',
        'paid_by_user_id',
        'note',
        'payment_gateway',
        'payment_reference',
        'payment_url',
        'payment_payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'tax_amount' => 'integer',
            'unique_code' => 'integer',
            'total' => 'integer',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'payment_confirmed_at' => 'datetime',
            'payment_payload' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** Potongan yang datang dari kode referal saja, tanpa promo perkenalan. */
    public function referralDiscount(): int
    {
        return max(0, (int) $this->discount_amount - (int) $this->intro_discount_amount);
    }

    /**
     * Tagihan ini mengisi saldo, bukan memperpanjang langganan.
     *
     * Dibaca dari `plan_slug` tagihan, bukan dari keadaan workspace: keadaan
     * workspace bisa berubah antara tagihan terbit dan dibayar, sementara
     * tagihan yang sudah terbit adalah janji yang tidak boleh berubah artinya.
     * Membeli PAYG dan mengisi saldo memang hal yang sama.
     */
    public function isTopup(): bool
    {
        return $this->plan_slug === 'payg';
    }

    /** Kode referal yang dipakai saat tagihan ini terbit, kalau ada. */
    public function referralCode(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class, 'referral_code_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    public function plan(): Plan
    {
        return Plan::find($this->plan_slug);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Pelanggan sudah konfirmasi pembayaran atau mengunggah bukti, menunggu ditinjau admin.
     */
    public function isAwaitingVerification(): bool
    {
        return $this->isPending() && (filled($this->payment_confirmed_at) || filled($this->proof_path));
    }

    /**
     * Sudah lewat batas bayar tapi belum ditandai kedaluwarsa oleh job harian.
     *
     * Halaman pembayaran memeriksanya sendiri, bukan menunggu job: tagihan yang
     * batas bayarnya lewat tengah malam tidak boleh masih menampilkan kode QR
     * yang aktif sampai job berikutnya berjalan.
     */
    public function isOverdue(): bool
    {
        return $this->isPending() && $this->due_at?->isPast();
    }

    public function periodLabel(): string
    {
        return $this->period === 'yearly' ? 'Tahunan' : 'Bulanan';
    }

    /**
     * Berapa lama periode ini memperpanjang langganan. Dipakai saat tagihan
     * ditandai lunas, jadi angkanya dibaca dari tagihan — bukan dari langganan
     * yang paketnya bisa saja sudah diganti sejak tagihan terbit.
     */
    public function periodMonths(): int
    {
        return $this->period === 'yearly' ? 12 : 1;
    }
}
