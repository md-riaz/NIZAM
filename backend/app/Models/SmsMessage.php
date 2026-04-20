<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsMessage extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_domain',
        'direction',
        'from_number',
        'to_number',
        'body',
        'status',
        'provider',
        'provider_message_id',
        'failure_reason',
        'metadata',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
