<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Queue extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'name',
        'strategy',
        'max_wait_time',
        'overflow_action',
        'overflow_destination',
        'music_on_hold',
        'service_level_threshold',
        'wrapup_seconds',
        'is_active',
    ];

    public const STRATEGY_RING_ALL = 'ring_all';

    public const STRATEGY_ROUND_ROBIN = 'round_robin';

    public const STRATEGY_LEAST_RECENT = 'least_recent';

    public const VALID_STRATEGIES = [
        self::STRATEGY_RING_ALL,
        self::STRATEGY_ROUND_ROBIN,
        self::STRATEGY_LEAST_RECENT,
    ];

    public const OVERFLOW_VOICEMAIL = 'voicemail';

    public const OVERFLOW_HANGUP = 'hangup';

    public const OVERFLOW_EXTENSION = 'extension';

    public const VALID_OVERFLOW_ACTIONS = [
        self::OVERFLOW_VOICEMAIL,
        self::OVERFLOW_HANGUP,
        self::OVERFLOW_EXTENSION,
    ];

    protected function casts(): array
    {
        return [
            'max_wait_time' => 'integer',
            'service_level_threshold' => 'integer',
            'wrapup_seconds' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'queue_members')
            ->withPivot('priority')
            ->withTimestamps();
    }

    public function entries(): HasMany
    {
        return $this->hasMany(QueueEntry::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(QueueMetric::class);
    }

    public function waitingEntries(): HasMany
    {
        return $this->entries()->where('status', QueueEntry::STATUS_WAITING);
    }
}
