<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusIncidentUpdate extends Model
{
    protected $fillable = ['status_incident_id', 'status', 'body', 'created_by'];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(StatusIncident::class, 'status_incident_id');
    }
}
