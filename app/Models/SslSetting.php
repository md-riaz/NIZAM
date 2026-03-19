<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SslSetting extends Model
{
    protected $fillable = [
        'email',
        'is_enabled',
        'domains',
        'status',
        'last_error',
        'last_renewed_at',
        'expires_at',
    ];

    protected $casts = [
        'domains' => 'array',
        'is_enabled' => 'boolean',
        'last_renewed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
