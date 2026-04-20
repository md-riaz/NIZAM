<?php

namespace Tests\Unit\Services;

use App\Models\AnalyticsEvent;
use App\Models\Organization;
use App\Services\InsightsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsightsServiceTest extends TestCase
{
    use RefreshDatabase;

    private InsightsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InsightsService;
    }

    public function test_score_event_with_perfect_call(): void
    {
        $organization = Organization::factory()->create();
        $event = AnalyticsEvent::factory()->create([
            'organization_id' => $organization->id,
            'wait_time' => 5,
            'talk_time' => 120,
            'abandon' => false,
            'hangup_cause' => 'NORMAL_CLEARING',
            'retries' => 0,
            'webhook_failures' => 0,
        ]);

        $scored = $this->service->scoreEvent($event);

        $this->assertEquals(100.00, (float) $scored->health_score);
        $this->assertIsArray($scored->score_breakdown);
        $this->assertEquals(100.0, $scored->score_breakdown['wait_time_score']);
        $this->assertEquals(100.0, $scored->score_breakdown['abandon_score']);
    }

    public function test_score_event_with_abandoned_call(): void
    {
        $event = AnalyticsEvent::factory()->create([
            'wait_time' => 90,
            'talk_time' => null,
            'abandon' => true,
            'hangup_cause' => 'ORIGINATOR_CANCEL',
            'retries' => 0,
            'webhook_failures' => 0,
        ]);

        $scored = $this->service->scoreEvent($event);

        $this->assertLessThan(50, (float) $scored->health_score);
        $this->assertEquals(0.0, $scored->score_breakdown['abandon_score']);
    }

    public function test_score_event_with_high_wait_time(): void
    {
        $event = AnalyticsEvent::factory()->create([
            'wait_time' => 150,
            'talk_time' => 100,
            'abandon' => false,
            'hangup_cause' => 'NORMAL_CLEARING',
            'retries' => 0,
            'webhook_failures' => 0,
        ]);

        $scored = $this->service->scoreEvent($event);

        $this->assertEquals(0.0, $scored->score_breakdown['wait_time_score']);
    }

    public function test_score_event_with_webhook_failures(): void
    {
        $event = AnalyticsEvent::factory()->create([
            'wait_time' => 10,
            'talk_time' => 100,
            'abandon' => false,
            'hangup_cause' => 'NORMAL_CLEARING',
            'retries' => 0,
            'webhook_failures' => 10,
        ]);

        $scored = $this->service->scoreEvent($event);

        $this->assertEquals(10.0, $scored->score_breakdown['webhook_score']);
    }

    public function test_compute_organization_health_score_with_no_events(): void
    {
        $organization = Organization::factory()->create();

        $result = $this->service->computeOrganizationHealthScore($organization->id);

        $this->assertEquals(100.0, $result['health_score']);
        $this->assertEquals(0, $result['sample_size']);
        $this->assertEquals($organization->id, $result['organization_id']);
    }

    public function test_compute_organization_health_score_with_events(): void
    {
        $organization = Organization::factory()->create();

        // Create and score events
        $event1 = AnalyticsEvent::factory()->create([
            'organization_id' => $organization->id,
            'health_score' => 80,
            'score_breakdown' => [
                'wait_time_score' => 80,
                'talk_time_score' => 100,
                'abandon_score' => 100,
                'hangup_cause_score' => 100,
                'retry_score' => 100,
                'webhook_score' => 100,
            ],
        ]);

        $event2 = AnalyticsEvent::factory()->create([
            'organization_id' => $organization->id,
            'health_score' => 60,
            'score_breakdown' => [
                'wait_time_score' => 60,
                'talk_time_score' => 100,
                'abandon_score' => 0,
                'hangup_cause_score' => 100,
                'retry_score' => 100,
                'webhook_score' => 100,
            ],
        ]);

        $result = $this->service->computeOrganizationHealthScore($organization->id);

        $this->assertEquals(70.0, $result['health_score']);
        $this->assertEquals(2, $result['sample_size']);
    }

    public function test_score_retries(): void
    {
        $event = AnalyticsEvent::factory()->create([
            'wait_time' => 5,
            'talk_time' => 100,
            'abandon' => false,
            'hangup_cause' => 'NORMAL_CLEARING',
            'retries' => 3,
            'webhook_failures' => 0,
        ]);

        $scored = $this->service->scoreEvent($event);

        $this->assertEquals(10.0, $scored->score_breakdown['retry_score']);
    }
}
