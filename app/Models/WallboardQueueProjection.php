<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WallboardQueueProjection extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'queue_id',
        'queue_name',
        'waiting_count',
        'calls_offered',
        'calls_answered',
        'calls_abandoned',
        'average_wait_time',
        'max_wait_time',
        'service_level',
        'abandon_rate',
        'agent_occupancy',
    ];

    protected function casts(): array
    {
        return [
            'waiting_count' => 'integer',
            'calls_offered' => 'integer',
            'calls_answered' => 'integer',
            'calls_abandoned' => 'integer',
            'average_wait_time' => 'decimal:2',
            'max_wait_time' => 'decimal:2',
            'service_level' => 'decimal:2',
            'abandon_rate' => 'decimal:2',
            'agent_occupancy' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function queue(): BelongsTo
    {
        return $this->belongsTo(Queue::class);
    }
}
