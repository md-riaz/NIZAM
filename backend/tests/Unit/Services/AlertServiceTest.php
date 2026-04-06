<?php

namespace Tests\Unit\Services;

use App\Models\Alert;
use App\Models\AlertPolicy;
use App\Services\AlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AlertServiceTest extends TestCase
{
    use RefreshDatabase;

    private AlertService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AlertService;
    }

    public function test_route_alert_to_email(): void
    {
        $policy = AlertPolicy::factory()->create([
            'channels' => [AlertPolicy::CHANNEL_EMAIL],
            'recipients' => ['alerts@example.com'],
        ]);

        $alert = Alert::factory()->create([
            'tenant_id' => $policy->tenant_id,
            'alert_policy_id' => $policy->id,
        ]);

        Log::shouldReceive('info')->once();

        $results = $this->service->routeAlert($alert);

        $this->assertArrayHasKey('email', $results);
        $this->assertEquals('queued', $results['email']['status']);
    }

    public function test_route_alert_to_webhook(): void
    {
        $policy = AlertPolicy::factory()->create([
            'channels' => [AlertPolicy::CHANNEL_WEBHOOK],
            'recipients' => ['https://hooks.example.com/alert'],
        ]);

        $alert = Alert::factory()->create([
            'tenant_id' => $policy->tenant_id,
            'alert_policy_id' => $policy->id,
        ]);

        Log::shouldReceive('info')->once();

        $results = $this->service->routeAlert($alert);

        $this->assertArrayHasKey('webhook', $results);
        $this->assertEquals('queued', $results['webhook']['status']);
        $this->assertArrayHasKey('payload', $results['webhook']);
    }

    public function test_route_alert_to_slack(): void
    {
        $policy = AlertPolicy::factory()->create([
            'channels' => [AlertPolicy::CHANNEL_SLACK],
            'recipients' => ['#alerts'],
        ]);

        $alert = Alert::factory()->create([
            'tenant_id' => $policy->tenant_id,
            'alert_policy_id' => $policy->id,
        ]);

        Log::shouldReceive('info')->once();

        $results = $this->service->routeAlert($alert);

        $this->assertArrayHasKey('slack', $results);
        $this->assertEquals('queued', $results['slack']['status']);
        $this->assertArrayHasKey('payload', $results['slack']);
    }

    public function test_route_alert_multiple_channels(): void
    {
        $policy = AlertPolicy::factory()->create([
            'channels' => [AlertPolicy::CHANNEL_EMAIL, AlertPolicy::CHANNEL_WEBHOOK],
            'recipients' => ['alerts@example.com'],
        ]);

        $alert = Alert::factory()->create([
            'tenant_id' => $policy->tenant_id,
            'alert_policy_id' => $policy->id,
        ]);

        Log::shouldReceive('info')->twice();

        $results = $this->service->routeAlert($alert);

        $this->assertArrayHasKey('email', $results);
        $this->assertArrayHasKey('webhook', $results);
    }

    public function test_webhook_payload_structure(): void
    {
        $policy = AlertPolicy::factory()->create([
            'channels' => [AlertPolicy::CHANNEL_WEBHOOK],
            'recipients' => ['https://hooks.example.com/alert'],
        ]);

        $alert = Alert::factory()->create([
            'tenant_id' => $policy->tenant_id,
            'alert_policy_id' => $policy->id,
            'severity' => Alert::SEVERITY_CRITICAL,
            'metric' => 'abandon_rate',
        ]);

        Log::shouldReceive('info')->once();

        $results = $this->service->routeAlert($alert);

        $payload = $results['webhook']['payload'];
        $this->assertArrayHasKey('alert_id', $payload);
        $this->assertArrayHasKey('tenant_id', $payload);
        $this->assertArrayHasKey('severity', $payload);
        $this->assertArrayHasKey('metric', $payload);
        $this->assertArrayHasKey('message', $payload);
    }

    public function test_slack_payload_has_emoji_by_severity(): void
    {
        $policy = AlertPolicy::factory()->create([
            'channels' => [AlertPolicy::CHANNEL_SLACK],
            'recipients' => ['#alerts'],
        ]);

        $alert = Alert::factory()->create([
            'tenant_id' => $policy->tenant_id,
            'alert_policy_id' => $policy->id,
            'severity' => Alert::SEVERITY_CRITICAL,
        ]);

        Log::shouldReceive('info')->once();

        $results = $this->service->routeAlert($alert);

        $this->assertStringContainsString(':rotating_light:', $results['slack']['payload']['text']);
    }

    public function test_route_alerts_batch(): void
    {
        $policy = AlertPolicy::factory()->create([
            'channels' => [AlertPolicy::CHANNEL_EMAIL],
            'recipients' => ['alerts@example.com'],
        ]);

        $alerts = Alert::factory()->count(3)->create([
            'tenant_id' => $policy->tenant_id,
            'alert_policy_id' => $policy->id,
        ]);

        Log::shouldReceive('info')->times(3);

        $results = $this->service->routeAlerts($alerts);

        $this->assertCount(3, $results);
    }
}
