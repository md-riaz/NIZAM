<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallFlow extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'nodes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'nodes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
