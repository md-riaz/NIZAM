<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    use HasFactory, HasUuids;

    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_INFO = 'info';

    public const VALID_SEVERITIES = [
        self::SEVERITY_CRITICAL,
        self::SEVERITY_WARNING,
        self::SEVERITY_INFO,
    ];

    public const STATUS_OPEN = 'open';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_RESOLVED = 'resolved';

    public const VALID_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_ACKNOWLEDGED,
        self::STATUS_RESOLVED,
    ];

    protected $fillable = [
        'tenant_id',
        'alert_policy_id',
        'severity',
        'metric',
        'current_value',
        'threshold_value',
        'status',
        'message',
        'context',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'current_value' => 'decimal:2',
            'threshold_value' => 'decimal:2',
            'context' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(AlertPolicy::class, 'alert_policy_id');
    }

    public function resolve(): self
    {
        $this->update([
            'status' => self::STATUS_RESOLVED,
            'resolved_at' => now(),
        ]);

        return $this;
    }

    public function acknowledge(): self
    {
        $this->update([
            'status' => self::STATUS_ACKNOWLEDGED,
        ]);

        return $this;
    }
}
