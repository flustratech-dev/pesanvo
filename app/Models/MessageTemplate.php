<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageTemplate extends Model
{
    protected $fillable = [
        'workspace_id',
        'name',
        'slug',
        'body',
        'variables',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Mengganti {{placeholder}} dengan nilai yang diberikan. Placeholder yang
     * tidak punya pasangan sengaja dibiarkan apa adanya supaya kesalahan
     * terlihat jelas di pesan, bukan hilang diam-diam jadi string kosong.
     */
    public function render(array $values): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            fn (array $m) => array_key_exists($m[1], $values) ? (string) $values[$m[1]] : $m[0],
            $this->body
        );
    }

    /**
     * Nama placeholder yang benar-benar dipakai body — dipakai saat menyimpan
     * template supaya kolom `variables` selalu sinkron dengan isinya.
     */
    public static function extractVariables(string $body): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $body, $matches);

        return array_values(array_unique($matches[1]));
    }
}
