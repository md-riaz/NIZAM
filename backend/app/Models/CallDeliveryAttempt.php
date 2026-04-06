<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallDeliveryAttempt extends Model
{
    use HasFactory, HasUuids;

    public const TYPE_SIP = 'sip';

    public const TYPE_PUSH = 'push';

    public const TYPE_PSTN = 'pstn';

    public const TYPE_LATE_SIP = 'late_sip';

    public const TYPE_CANCEL = 'cancel';

    public const TYPE_ANSWERED_ELSEWHERE = 'answered_elsewhere';

    public const VALID_ATTEMPT_TYPES = [
        self::TYPE_SIP,
        self::TYPE_PUSH,
        self::TYPE_PSTN,
        self::TYPE_LATE_SIP,
        self::TYPE_CANCEL,
        self::TYPE_ANSWERED_ELSEWHERE,
    ];

    public const STATUS_PLANNED = 'planned';

    public const STATUS_INITIATED = 'initiated';

    public const STATUS_RINGING = 'ringing';

    public const STATUS_ANSWERED = 'answered';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_WON = 'won';

    public const STATUS_LOST = 'lost';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    public const STATUS_TIMED_OUT = 'timed_out';

    public const VALID_STATUSES = [
        self::STATUS_PLANNED,
        self::STATUS_INITIATED,
        self::STATUS_RINGING,
        self::STATUS_ANSWERED,
        self::STATUS_CONFIRMED,
        self::STATUS_WON,
        self::STATUS_LOST,
        self::STATUS_CANCELLED,
        self::STATUS_FAILED,
        self::STATUS_TIMED_OUT,
    ];

    public const ACTIVE_STATUSES = [
        self::STATUS_PLANNED,
        self::STATUS_INITIATED,
        self::STATUS_RINGING,
        self::STATUS_ANSWERED,
        self::STATUS_CONFIRMED,
    ];

    protected $fillable = [
        'call_session_id',
        'endpoint_binding_id',
        'attempt_type',
        'status',
        'freeswitch_leg_uuid',
        'started_at',
        'answered_at',
        'ended_at',
        'failure_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'answered_at' => 'datetime',
            'ended_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function callSession(): BelongsTo
    {
        return $this->belongsTo(CallSession::class);
    }

    public function endpointBinding(): BelongsTo
    {
        return $this->belongsTo(EndpointBinding::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function scopeWinning(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_WON);
    }
}
