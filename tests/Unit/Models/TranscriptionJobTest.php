<?php

namespace Tests\Unit\Models;

use App\Models\Recording;
use App\Models\Tenant;
use App\Models\TranscriptionJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranscriptionJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_transcription_job(): void
    {
        $job = TranscriptionJob::factory()->create();

        $this->assertDatabaseHas('transcription_jobs', ['id' => $job->id]);
        $this->assertEquals(TranscriptionJob::STATUS_PENDING, $job->status);
    }

    public function test_belongs_to_recording(): void
    {
        $recording = Recording::factory()->create();
        $job = TranscriptionJob::factory()->create(['recording_id' => $recording->id, 'tenant_id' => $recording->tenant_id]);

        $this->assertInstanceOf(Recording::class, $job->recording);
    }

    public function test_mark_processing(): void
    {
        $job = TranscriptionJob::factory()->create();
        $job->markProcessing();

        $this->assertEquals(TranscriptionJob::STATUS_PROCESSING, $job->status);
        $this->assertNotNull($job->started_at);
        $this->assertEquals(1, $job->attempts);
    }

    public function test_mark_completed(): void
    {
        $job = TranscriptionJob::factory()->create();
        $job->markCompleted('Hello, this is a test transcript.', [['word' => 'Hello', 'start' => 0.0, 'end' => 0.5]]);

        $this->assertEquals(TranscriptionJob::STATUS_COMPLETED, $job->status);
        $this->assertEquals('Hello, this is a test transcript.', $job->transcript_text);
        $this->assertNotNull($job->transcript_timing);
        $this->assertNotNull($job->completed_at);
    }

    public function test_mark_failed(): void
    {
        $job = TranscriptionJob::factory()->create();
        $job->markFailed('Provider timeout');

        $this->assertEquals(TranscriptionJob::STATUS_FAILED, $job->status);
        $this->assertEquals('Provider timeout', $job->error_message);
    }

    public function test_can_retry_when_failed(): void
    {
        $job = TranscriptionJob::factory()->create([
            'status' => TranscriptionJob::STATUS_FAILED,
            'attempts' => 1,
        ]);

        $this->assertTrue($job->canRetry(3));
    }

    public function test_cannot_retry_when_max_attempts(): void
    {
        $job = TranscriptionJob::factory()->create([
            'status' => TranscriptionJob::STATUS_FAILED,
            'attempts' => 3,
        ]);

        $this->assertFalse($job->canRetry(3));
    }

    public function test_cannot_retry_when_not_failed(): void
    {
        $job = TranscriptionJob::factory()->create([
            'status' => TranscriptionJob::STATUS_COMPLETED,
            'attempts' => 1,
        ]);

        $this->assertFalse($job->canRetry(3));
    }
}
