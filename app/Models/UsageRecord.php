<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageRecord extends Model
{
    use HasFactory, HasUuids;

    public const METRIC_CALL_MINUTES = 'call_minutes';

    public const METRIC_CONCURRENT_CALL_PEAK = 'concurrent_call_peak';

    public const METRIC_RECORDING_STORAGE = 'recording_storage_bytes';

    public const METRIC_ACTIVE_DEVICES = 'active_devices';

    public const METRIC_ACTIVE_EXTENSIONS = 'active_extensions';

    protected $fillable = [
        'tenant_id',
        'metric',
        'value',
        'metadata',
        'recorded_date',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:4',
            'metadata' => 'array',
            'recorded_date' => 'date',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
