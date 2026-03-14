<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CallSession extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $fillable = [
        'call_uuid',
        'tenant_id',
        'did_id',
        'flow_version_id',
        'current_node_id',
        'state',
        'variables',
        'lock_version',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'lock_version' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function did(): BelongsTo
    {
        return $this->belongsTo(Did::class);
    }

    public function flowVersion(): BelongsTo
    {
        return $this->belongsTo(FlowVersion::class, 'flow_version_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CallEventLog::class);
    }

    public function traceEvents(): HasMany
    {
        return $this->hasMany(CallTraceEvent::class);
    }
}
