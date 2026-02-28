<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsEvent extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'call_uuid',
        'version',
        'wait_time',
        'talk_time',
        'abandon',
        'agent_id',
        'queue_id',
        'hangup_cause',
        'retries',
        'webhook_failures',
        'health_score',
        'score_breakdown',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'wait_time' => 'decimal:2',
            'talk_time' => 'decimal:2',
            'abandon' => 'boolean',
            'retries' => 'integer',
            'webhook_failures' => 'integer',
            'health_score' => 'decimal:2',
            'score_breakdown' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
