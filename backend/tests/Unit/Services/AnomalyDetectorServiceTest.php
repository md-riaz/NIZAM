<?php

namespace Tests\Unit\Services;

use App\Models\Alert;
use App\Models\AlertPolicy;
use App\Models\AnalyticsEvent;
use App\Models\Organization;
use App\Services\AnomalyDetectorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnomalyDetectorServiceTest extends TestCase
{
    use RefreshDatabase;

    private AnomalyDetectorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AnomalyDetectorService;
    }

    public function test_detect_abandon_rate_spike(): void
    {
        $organization = Organization::factory()->create();

        $policy = AlertPolicy::factory()->create([
            'organization_id' => $organization->id,
            'metric' => AlertPolicy::METRIC_ABANDON_RATE,
            'condition' => AlertPolicy::CONDITION_GT,
            'threshold' => 20,
            'window_minutes' => 60,
        ]);

        // Create events with high abandon rate (4 out of 5 = 80%)
        for ($i = 0; $i < 4; $i++) {
            AnalyticsEvent::factory()->create([
                'organization_id' => $organization->id,
                'abandon' => true,
            ]);
        }
        AnalyticsEvent::factory()->create([
            'organization_id' => $organization->id,
            'abandon' => false,
        ]);

        $alerts = $this->service->detectAnomalies($organization->id);

        $this->assertCount(1, $alerts);
        $this->assertEquals(AlertPolicy::METRIC_ABANDON_RATE, $alerts->first()->metric);
        $this->assertEquals(80.0, (float) $alerts->first()->current_value);
    }

    public function test_no_alert_when_below_threshold(): void
    {
        $organization = Organization::factory()->create();

        AlertPolicy::factory()->create([
            'organization_id' => $organization->id,
            'metric' => AlertPolicy::METRIC_ABANDON_RATE,
            'condition' => AlertPolicy::CONDITION_GT,
            'threshold' => 50,
            'window_minutes' => 60,
        ]);

        // Create events with low abandon rate (1 out of 10 = 10%)
        AnalyticsEvent::factory()->create([
            'organization_id' => $organization->id,
            'abandon' => true,
        ]);
        for ($i = 0; $i < 9; $i++) {
            AnalyticsEvent::factory()->create([
                'organization_id' => $organization->id,
                'abandon' => false,
            ]);
        }

        $alerts = $this->service->detectAnomalies($organization->id);

        $this->assertCount(0, $alerts);
    }

    public function test_cooldown_prevents_duplicate_alerts(): void
    {
        $organization = Organization::factory()->create();

        $policy = AlertPolicy::factory()->create([
            'organization_id' => $organization->id,
            'metric' => AlertPolicy::METRIC_ABANDON_RATE,
            'condition' => AlertPolicy::CONDITION_GT,
            'threshold' => 20,
            'window_minutes' => 60,
            'cooldown_minutes' => 15,
            'last_triggered_at' => now(),
        ]);

        AnalyticsEvent::factory()->count(5)->create([
            'organization_id' => $organization->id,
            'abandon' => true,
        ]);

        $alerts = $this->service->detectAnomalies($organization->id);

        $this->assertCount(0, $alerts);
    }

    public function test_inactive_policies_skipped(): void
    {
        $organization = Organization::factory()->create();

        AlertPolicy::factory()->create([
            'organization_id' => $organization->id,
            'metric' => AlertPolicy::METRIC_ABANDON_RATE,
            'condition' => AlertPolicy::CONDITION_GT,
            'threshold' => 0,
            'is_active' => false,
        ]);

        AnalyticsEvent::factory()->count(5)->create([
            'organization_id' => $organization->id,
            'abandon' => true,
        ]);

        $alerts = $this->service->detectAnomalies($organization->id);

        $this->assertCount(0, $alerts);
    }

    public function test_severity_determination(): void
    {
        $organization = Organization::factory()->create();

        // Create a policy with threshold 20, value will be 100% (critical deviation)
        $policy = AlertPolicy::factory()->create([
            'organization_id' => $organization->id,
            'metric' => AlertPolicy::METRIC_ABANDON_RATE,
            'condition' => AlertPolicy::CONDITION_GT,
            'threshold' => 20,
            'window_minutes' => 60,
        ]);

        AnalyticsEvent::factory()->count(5)->create([
            'organization_id' => $organization->id,
            'abandon' => true,
        ]);

        $alerts = $this->service->detectAnomalies($organization->id);

        $this->assertCount(1, $alerts);
        $this->assertEquals(Alert::SEVERITY_CRITICAL, $alerts->first()->severity);
    }

    public function test_alert_message_is_descriptive(): void
    {
        $organization = Organization::factory()->create();

        $policy = AlertPolicy::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'High Abandon Rate',
            'metric' => AlertPolicy::METRIC_ABANDON_RATE,
            'condition' => AlertPolicy::CONDITION_GT,
            'threshold' => 10,
            'window_minutes' => 60,
        ]);

        AnalyticsEvent::factory()->count(5)->create([
            'organization_id' => $organization->id,
            'abandon' => true,
        ]);

        $alert = $this->service->evaluatePolicy($policy);

        $this->assertNotNull($alert);
        $this->assertStringContainsString('High Abandon Rate', $alert->message);
        $this->assertStringContainsString('abandon rate', $alert->message);
    }

    public function test_sla_drop_detection(): void
    {
        $organization = Organization::factory()->create();

        $policy = AlertPolicy::factory()->create([
            'organization_id' => $organization->id,
            'metric' => AlertPolicy::METRIC_SLA_DROP,
            'condition' => AlertPolicy::CONDITION_LT,
            'threshold' => 80,
            'window_minutes' => 60,
        ]);

        // Create queue metrics with low SLA
        \App\Models\QueueMetric::factory()->create([
            'organization_id' => $organization->id,
            'service_level' => 50,
        ]);

        $alert = $this->service->evaluatePolicy($policy);

        $this->assertNotNull($alert);
        $this->assertEquals(AlertPolicy::METRIC_SLA_DROP, $alert->metric);
    }
}
