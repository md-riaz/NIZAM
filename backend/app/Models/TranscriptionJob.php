<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranscriptionJob extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
    ];

    protected $fillable = [
        'tenant_id',
        'recording_id',
        'status',
        'provider',
        'transcript_text',
        'transcript_timing',
        'language',
        'attempts',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'transcript_timing' => 'array',
            'attempts' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function recording(): BelongsTo
    {
        return $this->belongsTo(Recording::class);
    }

    /**
     * Check if the job can be retried.
     */
    public function canRetry(int $maxAttempts = 3): bool
    {
        return $this->status === self::STATUS_FAILED && $this->attempts < $maxAttempts;
    }

    /**
     * Mark the job as processing.
     */
    public function markProcessing(): self
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
            'attempts' => $this->attempts + 1,
        ]);

        return $this;
    }

    /**
     * Mark the job as completed.
     */
    public function markCompleted(string $text, ?array $timing = null): self
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'transcript_text' => $text,
            'transcript_timing' => $timing,
            'completed_at' => now(),
            'error_message' => null,
        ]);

        return $this;
    }

    /**
     * Mark the job as failed.
     */
    public function markFailed(string $error): self
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $error,
        ]);

        return $this;
    }
}
