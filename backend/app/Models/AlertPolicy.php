<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlertPolicy extends Model
{
    use HasFactory, HasUuids;

    public const METRIC_ABANDON_RATE = 'abandon_rate';

    public const METRIC_WEBHOOK_FAILURES = 'webhook_failures';

    public const METRIC_GATEWAY_FLAPPING = 'gateway_flapping';

    public const METRIC_SLA_DROP = 'sla_drop';

    public const VALID_METRICS = [
        self::METRIC_ABANDON_RATE,
        self::METRIC_WEBHOOK_FAILURES,
        self::METRIC_GATEWAY_FLAPPING,
        self::METRIC_SLA_DROP,
    ];

    public const CONDITION_GT = 'gt';

    public const CONDITION_LT = 'lt';

    public const CONDITION_GTE = 'gte';

    public const CONDITION_LTE = 'lte';

    public const CONDITION_EQ = 'eq';

    public const VALID_CONDITIONS = [
        self::CONDITION_GT,
        self::CONDITION_LT,
        self::CONDITION_GTE,
        self::CONDITION_LTE,
        self::CONDITION_EQ,
    ];

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_WEBHOOK = 'webhook';

    public const CHANNEL_SLACK = 'slack';

    public const VALID_CHANNELS = [
        self::CHANNEL_EMAIL,
        self::CHANNEL_WEBHOOK,
        self::CHANNEL_SLACK,
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'metric',
        'condition',
        'threshold',
        'window_minutes',
        'channels',
        'recipients',
        'is_active',
        'cooldown_minutes',
        'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'threshold' => 'decimal:2',
            'window_minutes' => 'integer',
            'channels' => 'array',
            'recipients' => 'array',
            'is_active' => 'boolean',
            'cooldown_minutes' => 'integer',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Check if the policy is in cooldown period.
     */
    public function isInCooldown(): bool
    {
        if (! $this->last_triggered_at) {
            return false;
        }

        return $this->last_triggered_at->addMinutes($this->cooldown_minutes)->isFuture();
    }

    /**
     * Evaluate the condition against a given value.
     */
    public function evaluateCondition(float $value): bool
    {
        return match ($this->condition) {
            self::CONDITION_GT => $value > (float) $this->threshold,
            self::CONDITION_LT => $value < (float) $this->threshold,
            self::CONDITION_GTE => $value >= (float) $this->threshold,
            self::CONDITION_LTE => $value <= (float) $this->threshold,
            self::CONDITION_EQ => abs($value - (float) $this->threshold) < 0.01,
            default => false,
        };
    }
}
