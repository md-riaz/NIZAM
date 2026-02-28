<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueEntry extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_WAITING = 'waiting';

    public const STATUS_RINGING = 'ringing';

    public const STATUS_ANSWERED = 'answered';

    public const STATUS_ABANDONED = 'abandoned';

    public const STATUS_OVERFLOWED = 'overflowed';

    public const VALID_STATUSES = [
        self::STATUS_WAITING,
        self::STATUS_RINGING,
        self::STATUS_ANSWERED,
        self::STATUS_ABANDONED,
        self::STATUS_OVERFLOWED,
    ];

    protected $fillable = [
        'tenant_id',
        'queue_id',
        'call_uuid',
        'caller_id_number',
        'caller_id_name',
        'status',
        'agent_id',
        'join_time',
        'answer_time',
        'abandon_time',
        'wait_duration',
        'abandon_reason',
    ];

    protected function casts(): array
    {
        return [
            'join_time' => 'datetime',
            'answer_time' => 'datetime',
            'abandon_time' => 'datetime',
            'wait_duration' => 'integer',
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

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
