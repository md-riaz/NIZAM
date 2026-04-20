<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceRegistrationSnapshot extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'endpoint_binding_id',
        'extension_id',
        'registration_key',
        'registered',
        'user_agent',
        'network_ip',
        'observed_at',
    ];

    protected function casts(): array
    {
        return [
            'registered' => 'boolean',
            'observed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function endpointBinding(): BelongsTo
    {
        return $this->belongsTo(EndpointBinding::class);
    }

    public function extension(): BelongsTo
    {
        return $this->belongsTo(Extension::class);
    }
}
