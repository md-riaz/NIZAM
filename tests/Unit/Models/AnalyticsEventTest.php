<?php

namespace Tests\Unit\Models;

use App\Models\AnalyticsEvent;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_analytics_event(): void
    {
        $tenant = Tenant::factory()->create();
        $event = AnalyticsEvent::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertDatabaseHas('analytics_events', ['id' => $event->id]);
        $this->assertEquals($tenant->id, $event->tenant_id);
    }

    public function test_belongs_to_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $event = AnalyticsEvent::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertInstanceOf(Tenant::class, $event->tenant);
        $this->assertEquals($tenant->id, $event->tenant->id);
    }

    public function test_idempotent_key_constraint(): void
    {
        $tenant = Tenant::factory()->create();
        $callUuid = fake()->uuid();

        AnalyticsEvent::factory()->create([
            'tenant_id' => $tenant->id,
            'call_uuid' => $callUuid,
            'version' => 1,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        AnalyticsEvent::factory()->create([
            'tenant_id' => $tenant->id,
            'call_uuid' => $callUuid,
            'version' => 1,
        ]);
    }

    public function test_allows_different_versions(): void
    {
        $tenant = Tenant::factory()->create();
        $callUuid = fake()->uuid();

        AnalyticsEvent::factory()->create([
            'tenant_id' => $tenant->id,
            'call_uuid' => $callUuid,
            'version' => 1,
        ]);

        $event2 = AnalyticsEvent::factory()->create([
            'tenant_id' => $tenant->id,
            'call_uuid' => $callUuid,
            'version' => 2,
        ]);

        $this->assertDatabaseHas('analytics_events', ['id' => $event2->id]);
    }

    public function test_casts(): void
    {
        $event = AnalyticsEvent::factory()->create([
            'abandon' => true,
            'score_breakdown' => ['wait_time_score' => 80],
        ]);

        $this->assertTrue($event->abandon);
        $this->assertIsArray($event->score_breakdown);
    }
}
