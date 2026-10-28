<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan bahwa seseorang sudah pernah melihat sebuah tur pengenalan.
 *
 * `skipped` disimpan apa adanya, tidak disamakan dengan `completed`. Keduanya
 * sama-sama berarti "jangan tampilkan lagi", tapi berapa banyak orang yang
 * melewatinya adalah satu-satunya tanda bahwa turnya sendiri yang bermasalah —
 * dan itu hilang begitu keduanya dicatat sama.
 */
class UserGuideProgress extends Model
{
    protected $table = 'user_guide_progress';

    protected $fillable = ['user_id', 'guide_key', 'guide_version', 'status'];

    protected function casts(): array
    {
        return ['guide_version' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
