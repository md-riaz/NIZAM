<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CallSession extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $fillable = [
        'call_uuid',
        'organization_id',
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

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
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

    public function deliveryAttempts(): HasMany
    {
        return $this->hasMany(CallDeliveryAttempt::class);
    }

    public function pushNotificationLogs(): HasMany
    {
        return $this->hasMany(PushNotificationLog::class);
    }

    public function winningDeliveryAttempt(): HasOne
    {
        return $this->hasOne(CallDeliveryAttempt::class)->where('status', CallDeliveryAttempt::STATUS_WON);
    }

    public function activeDeliveryAttempts(): HasMany
    {
        return $this->hasMany(CallDeliveryAttempt::class)
            ->whereIn('status', CallDeliveryAttempt::ACTIVE_STATUSES);
    }
}
