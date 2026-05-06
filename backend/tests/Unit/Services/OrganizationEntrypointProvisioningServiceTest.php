<?php

namespace Tests\Unit\Services;

use App\Models\Did;
use App\Models\Flow;
use App\Models\FlowNode;
use App\Models\Organization;
use App\Models\OrganizationDialplanManifest;
use App\Models\Schedule;
use App\Models\ScheduleRule;
use App\Models\Team;
use App\Models\TeamMember;
use App\Services\OrganizationEntrypointProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationEntrypointProvisioningServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_provision_creates_default_schedule_flow_did_and_manifest(): void
    {
        $organization = Organization::factory()->create([
            'domain' => 'starter.example.com',
        ]);

        $service = app(OrganizationEntrypointProvisioningService::class);
        $organization = $service->provision($organization);

        $organization->refresh();

        $this->assertNotNull($organization->default_schedule_id);

        $schedule = Schedule::findOrFail($organization->default_schedule_id);
        $this->assertSame('Main Business Hours', $schedule->name);
        $this->assertSame(5, $schedule->rules()->count());

        $starterExtension = $organization->extensions()->first();
        $this->assertNotNull($starterExtension);

        $flow = Flow::query()->where('organization_id', $organization->id)->where('name', 'Main Business Phone')->first();
        $this->assertNotNull($flow);
        $this->assertNotNull($flow->active_version_id);

        $nodeTypes = FlowNode::query()
            ->where('flow_version_id', $flow->active_version_id)
            ->pluck('type')
            ->all();

        $this->assertContains('start', $nodeTypes);
        $this->assertContains('schedule_check', $nodeTypes);
        $this->assertContains('play_message', $nodeTypes);
        $this->assertContains('voicemail', $nodeTypes);
        $this->assertContains('hangup', $nodeTypes);

        $openNode = FlowNode::query()
            ->where('flow_version_id', $flow->active_version_id)
            ->where('name', 'Main Extension')
            ->first();

        $this->assertNotNull($openNode);
        $this->assertSame('extension', data_get($openNode->config_json, 'destination_type'));
        $this->assertSame((string) $starterExtension->id, data_get($openNode->config_json, 'destination_value'));

        $did = Did::query()
            ->where('organization_id', $organization->id)
            ->where('description', 'Default Business Phone Entrypoint')
            ->first();

        $this->assertNotNull($did);
        $this->assertSame('flow', $did->destination_type);
        $this->assertSame((string) $flow->id, (string) $did->destination_id);

        $this->assertSame((string) $flow->id, data_get($organization->settings, 'business_phone.default_entrypoint.flow_id'));
        $this->assertSame((string) $schedule->id, data_get($organization->settings, 'business_phone.default_entrypoint.schedule_id'));
        $this->assertSame('extension', data_get($organization->settings, 'business_phone.default_entrypoint.open_target_type'));
        $this->assertSame((string) $starterExtension->id, data_get($organization->settings, 'business_phone.default_entrypoint.open_target_id'));
        $this->assertSame($starterExtension->extension, data_get($organization->settings, 'business_phone.default_entrypoint.operator_extension'));
        $this->assertTrue((bool) data_get($organization->settings, 'business_phone.default_entrypoint.provisioned'));
        $this->assertSame([
            'parking_enabled' => false,
            'pickup_enabled' => false,
            'paging_enabled' => false,
            'intercom_enabled' => false,
            'directory_enabled' => false,
        ], data_get($organization->settings, 'business_phone.office_features'));

        $manifest = OrganizationDialplanManifest::query()
            ->where('organization_id', $organization->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($manifest);
        $this->assertStringContainsString('flow_'.$flow->id, $manifest->content);
        $this->assertStringContainsString('schedule_'.$schedule->id, $manifest->content);
    }

    public function test_provision_uses_existing_main_team_as_open_target_and_adds_starter_extension_membership(): void
    {
        $organization = Organization::factory()->create([
            'domain' => 'team.example.com',
        ]);
        $team = Team::create([
            'organization_id' => $organization->id,
            'name' => 'Main Team',
            'strategy' => 'simultaneous',
            'timeout' => 20,
            'is_active' => true,
        ]);

        $service = app(OrganizationEntrypointProvisioningService::class);
        $organization = $service->provision($organization);

        $organization->refresh();
        $starterExtension = $organization->extensions()->first();
        $this->assertNotNull($starterExtension);

        $flow = $organization->flows()->where('name', 'Main Business Phone')->first();
        $this->assertNotNull($flow);
        $this->assertNotNull($flow->active_version_id);

        $openNode = FlowNode::query()
            ->where('flow_version_id', $flow->active_version_id)
            ->where('name', 'Main Team')
            ->first();

        $this->assertNotNull($openNode);
        $this->assertSame('ring_team', $openNode->type);
        $this->assertSame((string) $team->id, data_get($openNode->config_json, 'team_id'));
        $this->assertSame('team', data_get($organization->settings, 'business_phone.default_entrypoint.open_target_type'));
        $this->assertSame((string) $team->id, data_get($organization->settings, 'business_phone.default_entrypoint.open_target_id'));

        $membership = TeamMember::query()
            ->where('team_id', $team->id)
            ->where('endpoint_type', 'extension')
            ->where('endpoint_id', $starterExtension->id)
            ->first();

        $this->assertNotNull($membership);
        $this->assertTrue($membership->is_active);
    }

    public function test_provision_keeps_existing_main_team_membership_count_on_rerun(): void
    {
        $organization = Organization::factory()->create([
            'domain' => 'rerun-team.example.com',
        ]);
        $team = Team::create([
            'organization_id' => $organization->id,
            'name' => 'Main Team',
            'strategy' => 'simultaneous',
            'timeout' => 20,
            'is_active' => true,
        ]);

        $service = app(OrganizationEntrypointProvisioningService::class);
        $organization = $service->provision($organization);
        $starterExtension = $organization->extensions()->first();
        $this->assertNotNull($starterExtension);

        $organization = $service->provision($organization->fresh());

        $this->assertSame(1, TeamMember::query()
            ->where('team_id', $team->id)
            ->where('endpoint_type', 'extension')
            ->where('endpoint_id', $starterExtension->id)
            ->count());
    }

    public function test_provision_updates_existing_flow_when_open_target_changes_before_rerun(): void
    {
        $organization = Organization::factory()->create([
            'domain' => 'reroute-team.example.com',
        ]);

        $service = app(OrganizationEntrypointProvisioningService::class);
        $organization = $service->provision($organization);
        $starterExtension = $organization->extensions()->first();
        $this->assertNotNull($starterExtension);

        $flow = $organization->flows()->where('name', 'Main Business Phone')->first();
        $this->assertNotNull($flow);
        $this->assertNotNull($flow->active_version_id);

        Team::create([
            'organization_id' => $organization->id,
            'name' => 'Main Team',
            'strategy' => 'simultaneous',
            'timeout' => 20,
            'is_active' => true,
        ]);

        $organization = $service->provision($organization->fresh());
        $organization->refresh();
        $flow->refresh();

        $this->assertSame('team', data_get($organization->settings, 'business_phone.default_entrypoint.open_target_type'));
        $this->assertNotNull($flow->active_version_id);

        $openNode = FlowNode::query()
            ->where('flow_version_id', $flow->active_version_id)
            ->where('name', 'Main Team')
            ->first();

        $this->assertNotNull($openNode);
        $this->assertSame('ring_team', $openNode->type);
    }

    public function test_provision_is_idempotent_when_default_schedule_and_entrypoint_already_exist(): void
    {
        $organization = Organization::factory()->create([
            'domain' => 'existing.example.com',
        ]);

        $schedule = $organization->schedules()->create([
            'name' => 'Existing Schedule',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);
        $organization->update([
            'default_schedule_id' => $schedule->id,
            'settings' => [
                'business_phone' => [
                    'default_entrypoint' => [
                        'flow_id' => 'missing-flow',
                    ],
                ],
            ],
        ]);

        $service = app(OrganizationEntrypointProvisioningService::class);
        $organization = $service->provision($organization);
        $organization = $service->provision($organization->fresh());

        $organization->refresh();

        $this->assertSame((string) $schedule->id, (string) $organization->default_schedule_id);
        $this->assertSame(0, ScheduleRule::query()->where('schedule_id', $schedule->id)->count());
        $this->assertSame(1, $organization->flows()->count());
        $this->assertSame(1, $organization->dids()->where('description', 'Default Business Phone Entrypoint')->count());
        $this->assertSame(1, $organization->extensions()->count());
        $this->assertTrue((bool) data_get($organization->settings, 'business_phone.default_entrypoint.provisioned'));
        $this->assertSame([
            'parking_enabled' => false,
            'pickup_enabled' => false,
            'paging_enabled' => false,
            'intercom_enabled' => false,
            'directory_enabled' => false,
        ], data_get($organization->settings, 'business_phone.office_features'));
    }
}
