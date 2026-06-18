<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventStore extends Model
{
    protected $table = 'event_store';

    protected $fillable = [
        'aggregate_uuid',
        'aggregate_type',
        'event_type',
        'event_data',
        'version',
        'occurred_at',
    ];

    protected $casts = [
        'event_data' => 'array',
        'occurred_at' => 'datetime',
    ];
}
