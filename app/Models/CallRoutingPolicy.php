<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallRoutingPolicy extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'conditions',
        'match_destination_type',
        'match_destination_id',
        'no_match_destination_type',
        'no_match_destination_id',
        'priority',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
