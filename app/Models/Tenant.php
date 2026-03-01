<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use Auditable, HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    public const STATUS_TRIAL = 'trial';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_TERMINATED = 'terminated';

    public const VALID_STATUSES = [
        self::STATUS_TRIAL,
        self::STATUS_ACTIVE,
        self::STATUS_SUSPENDED,
        self::STATUS_TERMINATED,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'domain',
        'slug',
        'settings',
        'codec_policy',
        'max_extensions',
        'max_concurrent_calls',
        'max_dids',
        'max_ring_groups',
        'recording_retention_days',
        'max_calls_per_minute',
        'is_active',
        'status',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'max_concurrent_calls' => 0,
        'max_dids' => 0,
        'max_ring_groups' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'codec_policy' => 'array',
            'max_extensions' => 'integer',
            'max_concurrent_calls' => 'integer',
            'max_dids' => 'integer',
            'max_ring_groups' => 'integer',
            'recording_retention_days' => 'integer',
            'max_calls_per_minute' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function isOperational(): bool
    {
        return in_array($this->status, [self::STATUS_TRIAL, self::STATUS_ACTIVE]);
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(Extension::class);
    }

    public function dids(): HasMany
    {
        return $this->hasMany(Did::class);
    }

    public function ringGroups(): HasMany
    {
        return $this->hasMany(RingGroup::class);
    }

    public function ivrs(): HasMany
    {
        return $this->hasMany(Ivr::class);
    }

    public function timeConditions(): HasMany
    {
        return $this->hasMany(TimeCondition::class);
    }

    public function cdrs(): HasMany
    {
        return $this->hasMany(CallDetailRecord::class);
    }

    public function deviceProfiles(): HasMany
    {
        return $this->hasMany(DeviceProfile::class);
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(Webhook::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(Recording::class);
    }

    public function callRoutingPolicies(): HasMany
    {
        return $this->hasMany(CallRoutingPolicy::class);
    }

    public function callFlows(): HasMany
    {
        return $this->hasMany(CallFlow::class);
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    public function queues(): HasMany
    {
        return $this->hasMany(Queue::class);
    }

    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }

    public function alertPolicies(): HasMany
    {
        return $this->hasMany(AlertPolicy::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function gateways(): HasMany
    {
        return $this->hasMany(Gateway::class);
    }
}
