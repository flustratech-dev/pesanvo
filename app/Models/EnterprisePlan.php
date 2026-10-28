<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kesepakatan Enterprise yang berlaku untuk satu workspace.
 *
 * Bentuknya sengaja meniru `App\Support\Plan` — nama, harga per periode, dan
 * enam batas yang sama persis — supaya seluruh kode yang sudah membaca batas
 * paket tidak perlu tahu bedanya. Yang membedakannya cuma dari mana angkanya
 * datang: paket katalog dari `config/plans.php`, yang ini dari kesepakatan
 * dengan satu pelanggan.
 *
 * Kesepakatan lama TIDAK dihapus saat diganti, hanya dimatikan. Riwayat harga
 * yang pernah disepakati adalah hal pertama yang dicari orang saat ada
 * perselisihan tagihan.
 */
class EnterprisePlan extends Model
{
    protected $fillable = [
        'workspace_id',
        'lead_id',
        'name',
        'price_monthly',
        'price_yearly',
        'max_sessions',
        'monthly_message_quota',
        'max_api_keys',
        'max_members',
        'message_retention_days',
        'api_rate_limit_per_minute',
        'note',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'price_monthly' => 'integer',
            'price_yearly' => 'integer',
            'max_sessions' => 'integer',
            'monthly_message_quota' => 'integer',
            'max_api_keys' => 'integer',
            'max_members' => 'integer',
            'message_retention_days' => 'integer',
            'api_rate_limit_per_minute' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(EnterpriseLead::class, 'lead_id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Kesepakatan yang sedang berlaku untuk sebuah workspace, kalau ada. */
    public static function berlakuUntuk(int $workspaceId): ?self
    {
        return self::where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->latest('id')
            ->first();
    }

    public function price(string $period): int
    {
        return $period === 'yearly' ? $this->price_yearly : $this->price_monthly;
    }

    /**
     * Batas yang disalin ke kolom `workspaces` saat tagihannya lunas.
     *
     * Bentuknya sama persis dengan `Plan::limits()` supaya `markPaid()` bisa
     * memakai keduanya tanpa percabangan tambahan — percabangan di jalur itu
     * adalah tempat pelanggan membayar lalu tidak menerima apa yang dibelinya.
     *
     * @return array{max_sessions: int, monthly_message_quota: int, api_rate_limit_per_minute: int}
     */
    public function limits(): array
    {
        return [
            'max_sessions' => $this->max_sessions,
            'monthly_message_quota' => $this->monthly_message_quota,
            'api_rate_limit_per_minute' => $this->api_rate_limit_per_minute,
        ];
    }
}
