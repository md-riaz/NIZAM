<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'name',
        'strategy',
        'timeout',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'timeout' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }
}
