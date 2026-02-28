<?php

namespace App\Services;

use App\Models\Recording;
use App\Models\TranscriptionJob;
use Illuminate\Support\Collection;

class RecordingIntelligenceService
{
    /** Silence ratio threshold to flag for review. */
    protected const SILENCE_THRESHOLD = 0.40;

    /** Transfer count threshold to flag for review. */
    protected const TRANSFER_THRESHOLD = 2;

    /** Short call duration threshold (seconds). */
    protected const SHORT_CALL_THRESHOLD = 10;

    /**
     * Enrich a recording with intelligence metadata.
     */
    public function enrichRecording(Recording $recording): Recording
    {
        $reviewReasons = [];

        // Flag long silence ratio
        if ($recording->silence_ratio !== null && $recording->silence_ratio >= self::SILENCE_THRESHOLD) {
            $reviewReasons[] = 'high_silence_ratio';
        }

        // Flag repeated transfers
        if ($recording->transfer_count >= self::TRANSFER_THRESHOLD) {
            $reviewReasons[] = 'repeated_transfers';
        }

        // Flag very short calls (possible dropped calls)
        if ($recording->duration !== null && $recording->duration < self::SHORT_CALL_THRESHOLD) {
            $reviewReasons[] = 'short_call';
        }

        // Flag abandoned calls
        if ($recording->outcome === 'abandoned') {
            $reviewReasons[] = 'abandoned_call';
        }

        $needsReview = ! empty($reviewReasons);

        $recording->update([
            'needs_review' => $needsReview,
            'review_reasons' => $needsReview ? $reviewReasons : null,
            'sentiment' => $recording->sentiment ?? 'pending',
            'keywords' => $recording->keywords ?? [],
        ]);

        return $recording;
    }

    /**
     * Batch enrich recordings for a tenant.
     */
    public function enrichTenantRecordings(string $tenantId, ?int $limit = null): Collection
    {
        $query = Recording::where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->whereNull('sentiment')
                    ->orWhere('sentiment', 'pending');
            });

        if ($limit) {
            $query->limit($limit);
        }

        $recordings = $query->get();

        return $recordings->map(fn (Recording $r) => $this->enrichRecording($r));
    }

    /**
     * Queue a recording for transcription.
     */
    public function queueForTranscription(Recording $recording, string $language = 'en'): TranscriptionJob
    {
        // Check for existing pending/processing job
        $existing = TranscriptionJob::where('recording_id', $recording->id)
            ->whereIn('status', [TranscriptionJob::STATUS_PENDING, TranscriptionJob::STATUS_PROCESSING])
            ->first();

        if ($existing) {
            return $existing;
        }

        return TranscriptionJob::create([
            'tenant_id' => $recording->tenant_id,
            'recording_id' => $recording->id,
            'status' => TranscriptionJob::STATUS_PENDING,
            'language' => $language,
        ]);
    }

    /**
     * Get recordings that need review for a tenant.
     */
    public function getRecordingsNeedingReview(string $tenantId): Collection
    {
        return Recording::where('tenant_id', $tenantId)
            ->where('needs_review', true)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get transcription status summary for a tenant.
     */
    public function getTranscriptionStatus(string $tenantId): array
    {
        $jobs = TranscriptionJob::where('tenant_id', $tenantId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'pending' => $jobs[TranscriptionJob::STATUS_PENDING] ?? 0,
            'processing' => $jobs[TranscriptionJob::STATUS_PROCESSING] ?? 0,
            'completed' => $jobs[TranscriptionJob::STATUS_COMPLETED] ?? 0,
            'failed' => $jobs[TranscriptionJob::STATUS_FAILED] ?? 0,
        ];
    }
}
