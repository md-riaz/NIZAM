<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallEventLog extends Model
{
    use HasUuids;

    public const SCHEMA_VERSION = '1.0';

    /** Canonical event types */
    public const EVENT_CALL_CREATED = 'call.created';

    public const EVENT_CALL_ANSWERED = 'call.answered';

    public const EVENT_CALL_BRIDGED = 'call.bridged';

    public const EVENT_CALL_HANGUP = 'call.hangup';

    public const EVENT_VOICEMAIL_RECEIVED = 'voicemail.received';

    public const EVENT_DEVICE_REGISTERED = 'device.registered';

    public const EVENT_DEVICE_UNREGISTERED = 'device.unregistered';

    public const CANONICAL_EVENTS = [
        self::EVENT_CALL_CREATED,
        self::EVENT_CALL_ANSWERED,
        self::EVENT_CALL_BRIDGED,
        self::EVENT_CALL_HANGUP,
        self::EVENT_VOICEMAIL_RECEIVED,
        self::EVENT_DEVICE_REGISTERED,
        self::EVENT_DEVICE_UNREGISTERED,
    ];

    protected $table = 'call_events';

    protected $fillable = [
        'tenant_id',
        'call_uuid',
        'event_type',
        'payload',
        'schema_version',
        'occurred_at',
    ];

    protected $attributes = [
        'schema_version' => self::SCHEMA_VERSION,
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
