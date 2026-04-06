<?php

namespace Tests\Unit\Models;

use App\Models\Alert;
use App\Models\AlertPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_alert(): void
    {
        $alert = Alert::factory()->create();

        $this->assertDatabaseHas('alerts', ['id' => $alert->id]);
        $this->assertEquals(Alert::STATUS_OPEN, $alert->status);
    }

    public function test_belongs_to_policy(): void
    {
        $policy = AlertPolicy::factory()->create();
        $alert = Alert::factory()->create([
            'tenant_id' => $policy->tenant_id,
            'alert_policy_id' => $policy->id,
        ]);

        $this->assertInstanceOf(AlertPolicy::class, $alert->policy);
    }

    public function test_resolve(): void
    {
        $alert = Alert::factory()->create();
        $alert->resolve();

        $this->assertEquals(Alert::STATUS_RESOLVED, $alert->status);
        $this->assertNotNull($alert->resolved_at);
    }

    public function test_acknowledge(): void
    {
        $alert = Alert::factory()->create();
        $alert->acknowledge();

        $this->assertEquals(Alert::STATUS_ACKNOWLEDGED, $alert->status);
    }

    public function test_valid_severities(): void
    {
        $this->assertContains('critical', Alert::VALID_SEVERITIES);
        $this->assertContains('warning', Alert::VALID_SEVERITIES);
        $this->assertContains('info', Alert::VALID_SEVERITIES);
    }
}
