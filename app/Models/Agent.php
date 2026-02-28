<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Agent extends Model
{
    use Auditable, HasFactory, HasUuids;

    public const ROLE_AGENT = 'agent';

    public const ROLE_SUPERVISOR = 'supervisor';

    public const VALID_ROLES = [
        self::ROLE_AGENT,
        self::ROLE_SUPERVISOR,
    ];

    public const STATE_AVAILABLE = 'available';

    public const STATE_BUSY = 'busy';

    public const STATE_RINGING = 'ringing';

    public const STATE_PAUSED = 'paused';

    public const STATE_OFFLINE = 'offline';

    public const VALID_STATES = [
        self::STATE_AVAILABLE,
        self::STATE_BUSY,
        self::STATE_RINGING,
        self::STATE_PAUSED,
        self::STATE_OFFLINE,
    ];

    public const PAUSE_BREAK = 'break';

    public const PAUSE_LUNCH = 'lunch';

    public const PAUSE_AFTER_CALL_WORK = 'after_call_work';

    public const PAUSE_CUSTOM = 'custom';

    public const DEFAULT_PAUSE_REASONS = [
        self::PAUSE_BREAK,
        self::PAUSE_LUNCH,
        self::PAUSE_AFTER_CALL_WORK,
        self::PAUSE_CUSTOM,
    ];

    protected $fillable = [
        'tenant_id',
        'extension_id',
        'name',
        'role',
        'state',
        'pause_reason',
        'state_changed_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'state_changed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function extension(): BelongsTo
    {
        return $this->belongsTo(Extension::class);
    }

    public function queues(): BelongsToMany
    {
        return $this->belongsToMany(Queue::class, 'queue_members')
            ->withPivot('priority')
            ->withTimestamps();
    }

    public function isAvailable(): bool
    {
        return $this->state === self::STATE_AVAILABLE && $this->is_active;
    }

    public function transitionState(string $newState, ?string $pauseReason = null): void
    {
        $this->state = $newState;
        $this->pause_reason = $newState === self::STATE_PAUSED ? $pauseReason : null;
        $this->state_changed_at = now();
        $this->save();
    }
}
