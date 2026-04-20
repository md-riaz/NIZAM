<?php

namespace Tests\Unit\Models;

use App\Models\AnalyticsEvent;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_analytics_event(): void
    {
        $organization = Organization::factory()->create();
        $event = AnalyticsEvent::factory()->create(['organization_id' => $organization->id]);

        $this->assertDatabaseHas('analytics_events', ['id' => $event->id]);
        $this->assertEquals($organization->id, $event->organization_id);
    }

    public function test_belongs_to_organization(): void
    {
        $organization = Organization::factory()->create();
        $event = AnalyticsEvent::factory()->create(['organization_id' => $organization->id]);

        $this->assertInstanceOf(Organization::class, $event->organization);
        $this->assertEquals($organization->id, $event->organization->id);
    }

    public function test_idempotent_key_constraint(): void
    {
        $organization = Organization::factory()->create();
        $callUuid = fake()->uuid();

        AnalyticsEvent::factory()->create([
            'organization_id' => $organization->id,
            'call_uuid' => $callUuid,
            'version' => 1,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        AnalyticsEvent::factory()->create([
            'organization_id' => $organization->id,
            'call_uuid' => $callUuid,
            'version' => 1,
        ]);
    }

    public function test_allows_different_versions(): void
    {
        $organization = Organization::factory()->create();
        $callUuid = fake()->uuid();

        AnalyticsEvent::factory()->create([
            'organization_id' => $organization->id,
            'call_uuid' => $callUuid,
            'version' => 1,
        ]);

        $event2 = AnalyticsEvent::factory()->create([
            'organization_id' => $organization->id,
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
