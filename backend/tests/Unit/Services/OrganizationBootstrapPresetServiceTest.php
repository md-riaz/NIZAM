<?php

namespace Tests\Unit\Services;

use App\Models\Organization;
use App\Models\Schedule;
use App\Services\Organization\OrganizationBootstrapPresetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationBootstrapPresetServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_preset_targets_starter_extension_and_normalizes_settings(): void
    {
        $organization = Organization::factory()->create();
        $schedule = Schedule::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Main Business Hours',
        ]);

        $preset = app(OrganizationBootstrapPresetService::class)->defaultPreset(
            $schedule,
            'extension',
            'extension-123',
            '101',
        );

        $this->assertSame('Main Business Phone', data_get($preset, 'flow.name'));
        $this->assertSame('flow', data_get($preset, 'default_entrypoint.type'));
        $this->assertSame((string) $schedule->id, data_get($preset, 'default_entrypoint.schedule_id'));
        $this->assertSame('extension', data_get($preset, 'default_entrypoint.open_target_type'));
        $this->assertSame('extension-123', data_get($preset, 'default_entrypoint.open_target_id'));
        $this->assertSame('101', data_get($preset, 'default_entrypoint.operator_extension'));
        $this->assertTrue((bool) data_get($preset, 'default_entrypoint.provisioned'));

        $nodes = collect(data_get($preset, 'flow.definition.nodes', []))->keyBy('id');
        $edges = collect(data_get($preset, 'flow.definition.edges', []));

        $this->assertSame('play_message', data_get($nodes['starter-open'], 'type'));
        $this->assertSame('extension', data_get($nodes['starter-open'], 'config.destination_type'));
        $this->assertSame('extension-123', data_get($nodes['starter-open'], 'config.destination_value'));
        $this->assertSame('voicemail', data_get($nodes['starter-after-hours'], 'type'));
        $this->assertTrue($edges->contains([
            'source_node_id' => 'starter-business-hours',
            'target_node_id' => 'starter-open',
            'condition' => 'open',
        ]));
        $this->assertTrue($edges->contains([
            'source_node_id' => 'starter-business-hours',
            'target_node_id' => 'starter-after-hours',
            'condition' => 'holiday',
        ]));
        $this->assertSame([
            'parking_enabled' => false,
            'pickup_enabled' => false,
            'paging_enabled' => false,
            'intercom_enabled' => false,
            'directory_enabled' => false,
        ], data_get($preset, 'office_features'));
    }

    public function test_default_preset_can_target_team_for_open_hours(): void
    {
        $organization = Organization::factory()->create();
        $schedule = Schedule::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $preset = app(OrganizationBootstrapPresetService::class)->defaultPreset(
            $schedule,
            'team',
            'team-123',
            '101',
        );

        $nodes = collect(data_get($preset, 'flow.definition.nodes', []))->keyBy('id');
        $edges = collect(data_get($preset, 'flow.definition.edges', []));

        $this->assertSame('ring_team', data_get($nodes['starter-open'], 'type'));
        $this->assertSame('team-123', data_get($nodes['starter-open'], 'config.team_id'));
        $this->assertTrue($edges->contains([
            'source_node_id' => 'starter-open',
            'target_node_id' => 'starter-complete',
            'condition' => 'no_answer',
        ]));
    }

    public function test_normalize_office_features_fills_missing_flags_and_preserves_existing_values(): void
    {
        $features = app(OrganizationBootstrapPresetService::class)->normalizeOfficeFeatures([
            'parking_enabled' => 1,
            'directory_enabled' => true,
        ]);

        $this->assertSame([
            'parking_enabled' => true,
            'pickup_enabled' => false,
            'paging_enabled' => false,
            'intercom_enabled' => false,
            'directory_enabled' => true,
        ], $features);
    }
}
