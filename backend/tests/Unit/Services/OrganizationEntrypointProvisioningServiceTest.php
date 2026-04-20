<?php

namespace Tests\Unit\Services;

use App\Models\Did;
use App\Models\Flow;
use App\Models\FlowNode;
use App\Models\Schedule;
use App\Models\ScheduleRule;
use App\Models\Organization;
use App\Models\OrganizationDialplanManifest;
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

        $flow = Flow::query()->where('organization_id', $organization->id)->where('name', 'Main Business Phone')->first();
        $this->assertNotNull($flow);
        $this->assertNotNull($flow->active_version_id);

        $nodeTypes = FlowNode::query()
            ->where('flow_version_id', $flow->active_version_id)
            ->pluck('type')
            ->all();

        $this->assertContains('start', $nodeTypes);
        $this->assertContains('schedule_check', $nodeTypes);
        $this->assertContains('voicemail', $nodeTypes);
        $this->assertContains('hangup', $nodeTypes);

        $did = Did::query()
            ->where('organization_id', $organization->id)
            ->where('description', 'Default Business Phone Entrypoint')
            ->first();

        $this->assertNotNull($did);
        $this->assertSame('flow', $did->destination_type);
        $this->assertSame((string) $flow->id, (string) $did->destination_id);

        $this->assertSame((string) $flow->id, data_get($organization->settings, 'business_phone.default_entrypoint.flow_id'));
        $this->assertSame((string) $schedule->id, data_get($organization->settings, 'business_phone.default_entrypoint.schedule_id'));
        $this->assertTrue((bool) data_get($organization->settings, 'business_phone.default_entrypoint.provisioned'));

        $manifest = OrganizationDialplanManifest::query()
            ->where('organization_id', $organization->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($manifest);
        $this->assertStringContainsString('flow_'.$flow->id, $manifest->content);
        $this->assertStringContainsString('schedule_'.$schedule->id, $manifest->content);
    }

    public function test_provision_is_additive_when_default_schedule_and_entrypoint_already_exist(): void
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

        $organization->refresh();

        $this->assertSame((string) $schedule->id, (string) $organization->default_schedule_id);
        $this->assertSame(0, ScheduleRule::query()->where('schedule_id', $schedule->id)->count());
        $this->assertSame(1, $organization->flows()->count());
        $this->assertSame(1, $organization->dids()->where('description', 'Default Business Phone Entrypoint')->count());
    }
}
