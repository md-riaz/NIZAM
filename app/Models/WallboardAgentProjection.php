<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WallboardAgentProjection extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'agent_id',
        'name',
        'role',
        'state',
        'pause_reason',
        'state_changed_at',
        'extension',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'state_changed_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
