<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EndpointBinding extends Model
{
    use Auditable, HasFactory, HasUuids;

    public const TYPE_DESK_PHONE = 'desk_phone';

    public const TYPE_MOBILE_APP = 'mobile_app';

    public const TYPE_PSTN_FORWARD = 'pstn_forward';

    public const TYPE_SOFTPHONE = 'softphone';

    public const TYPE_AGENT_ENDPOINT = 'agent_endpoint';

    public const VALID_TYPES = [
        self::TYPE_DESK_PHONE,
        self::TYPE_MOBILE_APP,
        self::TYPE_PSTN_FORWARD,
        self::TYPE_SOFTPHONE,
        self::TYPE_AGENT_ENDPOINT,
    ];

    public const PLATFORM_IOS = 'ios';

    public const PLATFORM_ANDROID = 'android';

    public const PLATFORM_WEB = 'web';

    public const PLATFORM_UNKNOWN = 'unknown';

    public const VALID_PLATFORMS = [
        self::PLATFORM_IOS,
        self::PLATFORM_ANDROID,
        self::PLATFORM_WEB,
        self::PLATFORM_UNKNOWN,
    ];

    protected $fillable = [
        'organization_id',
        'extension_id',
        'agent_id',
        'type',
        'device_uuid',
        'push_token',
        'voip_push_token',
        'platform',
        'app_version',
        'is_push_capable',
        'is_enabled',
        'rings_immediately_when_online',
        'allow_late_join_after_push',
        'forward_number',
        'forward_requires_confirm',
        'last_seen_at',
        'last_registered_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_push_capable' => 'boolean',
            'is_enabled' => 'boolean',
            'rings_immediately_when_online' => 'boolean',
            'allow_late_join_after_push' => 'boolean',
            'forward_requires_confirm' => 'boolean',
            'last_seen_at' => 'datetime',
            'last_registered_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function extension(): BelongsTo
    {
        return $this->belongsTo(Extension::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function deliveryAttempts(): HasMany
    {
        return $this->hasMany(CallDeliveryAttempt::class);
    }

    public function pushNotificationLogs(): HasMany
    {
        return $this->hasMany(PushNotificationLog::class);
    }

    public function deviceRegistrationSnapshots(): HasMany
    {
        return $this->hasMany(DeviceRegistrationSnapshot::class);
    }

    public function pushEnabled(): bool
    {
        return (bool) data_get($this->metadata, 'push_enabled', $this->is_push_capable);
    }

    public function hasPushTokenMaterial(): bool
    {
        return filled($this->push_token) || filled($this->voip_push_token);
    }

    public function runtimeConfigurationErrors(): array
    {
        $errors = [];

        if (($this->pushEnabled() || $this->is_push_capable) && ! $this->hasPushTokenMaterial()) {
            $errors[] = 'Push-capable endpoints require push token material.';
        }

        if ($this->type === self::TYPE_PSTN_FORWARD && blank($this->forward_number)) {
            $errors[] = 'PSTN forward endpoints require a forward number.';
        }

        return $errors;
    }

    public function isRuntimeConfigurationValid(): bool
    {
        return $this->runtimeConfigurationErrors() === [];
    }

    public function isEligibleForOrchestration(): bool
    {
        return $this->is_enabled && $this->isRuntimeConfigurationValid();
    }
}
