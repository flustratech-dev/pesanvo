<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'workspace_id',
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'context',
        'ip_address',
    ];

    protected function casts(): array
    {
        return ['context' => 'array'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, ?Model $subject = null, array $context = [], ?int $workspaceId = null): self
    {
        return self::create([
            'workspace_id' => $workspaceId ?? session('current_workspace_id'),
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'context' => $context ?: null,
            'ip_address' => request()->ip(),
        ]);
    }
}
