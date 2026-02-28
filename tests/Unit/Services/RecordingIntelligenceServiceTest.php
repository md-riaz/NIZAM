<?php

namespace Tests\Unit\Services;

use App\Models\Recording;
use App\Models\Tenant;
use App\Models\TranscriptionJob;
use App\Services\RecordingIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordingIntelligenceServiceTest extends TestCase
{
    use RefreshDatabase;

    private RecordingIntelligenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RecordingIntelligenceService;
    }

    public function test_enrich_recording_flags_high_silence(): void
    {
        $recording = Recording::factory()->create([
            'silence_ratio' => 0.50,
            'transfer_count' => 0,
            'duration' => 120,
        ]);

        $enriched = $this->service->enrichRecording($recording);

        $this->assertTrue($enriched->needs_review);
        $this->assertContains('high_silence_ratio', $enriched->review_reasons);
    }

    public function test_enrich_recording_flags_repeated_transfers(): void
    {
        $recording = Recording::factory()->create([
            'silence_ratio' => 0.10,
            'transfer_count' => 3,
            'duration' => 120,
        ]);

        $enriched = $this->service->enrichRecording($recording);

        $this->assertTrue($enriched->needs_review);
        $this->assertContains('repeated_transfers', $enriched->review_reasons);
    }

    public function test_enrich_recording_flags_short_call(): void
    {
        $recording = Recording::factory()->create([
            'silence_ratio' => 0.10,
            'transfer_count' => 0,
            'duration' => 5,
        ]);

        $enriched = $this->service->enrichRecording($recording);

        $this->assertTrue($enriched->needs_review);
        $this->assertContains('short_call', $enriched->review_reasons);
    }

    public function test_enrich_recording_flags_abandoned(): void
    {
        $recording = Recording::factory()->create([
            'silence_ratio' => 0.10,
            'transfer_count' => 0,
            'duration' => 120,
            'outcome' => 'abandoned',
        ]);

        $enriched = $this->service->enrichRecording($recording);

        $this->assertTrue($enriched->needs_review);
        $this->assertContains('abandoned_call', $enriched->review_reasons);
    }

    public function test_enrich_recording_no_flags(): void
    {
        $recording = Recording::factory()->create([
            'silence_ratio' => 0.10,
            'transfer_count' => 0,
            'duration' => 120,
            'outcome' => 'answered',
        ]);

        $enriched = $this->service->enrichRecording($recording);

        $this->assertFalse($enriched->needs_review);
        $this->assertNull($enriched->review_reasons);
    }

    public function test_enrich_recording_sets_sentiment_placeholder(): void
    {
        $recording = Recording::factory()->create([
            'sentiment' => null,
        ]);

        $enriched = $this->service->enrichRecording($recording);

        $this->assertEquals('pending', $enriched->sentiment);
    }

    public function test_queue_for_transcription(): void
    {
        $recording = Recording::factory()->create();

        $job = $this->service->queueForTranscription($recording);

        $this->assertInstanceOf(TranscriptionJob::class, $job);
        $this->assertEquals(TranscriptionJob::STATUS_PENDING, $job->status);
        $this->assertEquals($recording->id, $job->recording_id);
    }

    public function test_queue_for_transcription_idempotent(): void
    {
        $recording = Recording::factory()->create();

        $job1 = $this->service->queueForTranscription($recording);
        $job2 = $this->service->queueForTranscription($recording);

        $this->assertEquals($job1->id, $job2->id);
    }

    public function test_batch_enrich_tenant_recordings(): void
    {
        $tenant = Tenant::factory()->create();
        Recording::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
            'sentiment' => null,
            'silence_ratio' => 0.50,
        ]);

        $enriched = $this->service->enrichTenantRecordings($tenant->id);

        $this->assertCount(3, $enriched);
        $enriched->each(fn ($r) => $this->assertTrue($r->needs_review));
    }

    public function test_get_recordings_needing_review(): void
    {
        $tenant = Tenant::factory()->create();
        Recording::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'needs_review' => true,
        ]);
        Recording::factory()->create([
            'tenant_id' => $tenant->id,
            'needs_review' => false,
        ]);

        $needsReview = $this->service->getRecordingsNeedingReview($tenant->id);

        $this->assertCount(2, $needsReview);
    }

    public function test_get_transcription_status(): void
    {
        $tenant = Tenant::factory()->create();
        TranscriptionJob::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'status' => TranscriptionJob::STATUS_PENDING,
        ]);
        TranscriptionJob::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => TranscriptionJob::STATUS_COMPLETED,
        ]);

        $status = $this->service->getTranscriptionStatus($tenant->id);

        $this->assertEquals(2, $status['pending']);
        $this->assertEquals(1, $status['completed']);
        $this->assertEquals(0, $status['processing']);
        $this->assertEquals(0, $status['failed']);
    }
}
