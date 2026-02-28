<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recording extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'tenant_id',
        'call_uuid',
        'file_path',
        'file_name',
        'file_size',
        'format',
        'duration',
        'direction',
        'caller_id_number',
        'destination_number',
        'queue_name',
        'agent_id',
        'wait_time',
        'outcome',
        'abandon_reason',
        'sentiment',
        'keywords',
        'needs_review',
        'review_reasons',
        'silence_ratio',
        'transfer_count',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'duration' => 'integer',
            'wait_time' => 'integer',
            'keywords' => 'array',
            'needs_review' => 'boolean',
            'review_reasons' => 'array',
            'silence_ratio' => 'decimal:2',
            'transfer_count' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the CDR associated with this recording via call UUID.
     */
    public function cdr(): BelongsTo
    {
        return $this->belongsTo(CallDetailRecord::class, 'call_uuid', 'uuid');
    }

    public function transcriptionJobs(): HasMany
    {
        return $this->hasMany(TranscriptionJob::class);
    }
}
