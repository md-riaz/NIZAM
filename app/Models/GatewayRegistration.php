<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GatewayRegistration extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $fillable = [
        'gateway_id',
        'registration_identifier',
        'username',
        'realm',
        'proxy',
        'transport',
        'status',
        'last_registered_at',
        'last_failed_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'last_registered_at' => 'datetime',
            'last_failed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class);
    }

    public function dids(): HasMany
    {
        return $this->hasMany(Did::class);
    }
}
