<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends Model
{
    protected $fillable = [
        'workspace_id',
        'api_key_id',
        'url',
        'secret',
        'events',
        'is_active',
        'last_success_at',
        'last_failure_at',
        'consecutive_failures',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * API key yang memiliki webhook ini, kalau ada.
     *
     * `null` berarti webhook tingkat workspace — ia menerima kejadian yang
     * tidak punya API key pemicu, dan itu perilaku bawaan seluruh baris yang
     * dibuat sebelum kolom ini ada.
     */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    /** Webhook tingkat workspace: berlaku untuk seluruh integrasi. */
    public function untukSeluruhWorkspace(): bool
    {
        return $this->api_key_id === null;
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function listensTo(string $event): bool
    {
        // events null berarti berlangganan semua event.
        return $this->events === null || in_array($event, $this->events, true);
    }
}
