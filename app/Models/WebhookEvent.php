<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'external_event_id',
        'event_type_raw',
        'event_type_canonical',
        'payload_json',
        'headers_json',
        'signature_verified_at',
        'processing_status',
        'attempt_count',
        'failure_reason',
        'processed_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'headers_json' => 'array',
        'signature_verified_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}

