<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushNotificationLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'call_session_id',
        'endpoint_binding_id',
        'push_type',
        'provider_message_id',
        'status',
        'sent_at',
        'response_payload',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'response_payload' => 'array',
        ];
    }

    public function callSession(): BelongsTo
    {
        return $this->belongsTo(CallSession::class);
    }

    public function endpointBinding(): BelongsTo
    {
        return $this->belongsTo(EndpointBinding::class);
    }
}
