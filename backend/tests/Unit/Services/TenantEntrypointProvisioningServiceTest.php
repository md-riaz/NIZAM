<?php

namespace Tests\Unit\Services;

use App\Models\Did;
use App\Models\Flow;
use App\Models\FlowNode;
use App\Models\Schedule;
use App\Models\ScheduleRule;
use App\Models\Tenant;
use App\Models\TenantDialplanManifest;
use App\Services\TenantEntrypointProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantEntrypointProvisioningServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_provision_creates_default_schedule_flow_did_and_manifest(): void
    {
        $tenant = Tenant::factory()->create([
            'domain' => 'starter.example.com',
        ]);

        $service = app(TenantEntrypointProvisioningService::class);
        $tenant = $service->provision($tenant);

        $tenant->refresh();

        $this->assertNotNull($tenant->default_schedule_id);

        $schedule = Schedule::findOrFail($tenant->default_schedule_id);
        $this->assertSame('Main Business Hours', $schedule->name);
        $this->assertSame(5, $schedule->rules()->count());

        $flow = Flow::query()->where('tenant_id', $tenant->id)->where('name', 'Main Business Phone')->first();
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
            ->where('tenant_id', $tenant->id)
            ->where('description', 'Default Business Phone Entrypoint')
            ->first();

        $this->assertNotNull($did);
        $this->assertSame('flow', $did->destination_type);
        $this->assertSame((string) $flow->id, (string) $did->destination_id);

        $this->assertSame((string) $flow->id, data_get($tenant->settings, 'business_phone.default_entrypoint.flow_id'));
        $this->assertSame((string) $schedule->id, data_get($tenant->settings, 'business_phone.default_entrypoint.schedule_id'));
        $this->assertTrue((bool) data_get($tenant->settings, 'business_phone.default_entrypoint.provisioned'));

        $manifest = TenantDialplanManifest::query()
            ->where('tenant_id', $tenant->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($manifest);
        $this->assertStringContainsString('flow_'.$flow->id, $manifest->content);
        $this->assertStringContainsString('schedule_'.$schedule->id, $manifest->content);
    }

    public function test_provision_is_additive_when_default_schedule_and_entrypoint_already_exist(): void
    {
        $tenant = Tenant::factory()->create([
            'domain' => 'existing.example.com',
        ]);

        $schedule = $tenant->schedules()->create([
            'name' => 'Existing Schedule',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);
        $tenant->update([
            'default_schedule_id' => $schedule->id,
            'settings' => [
                'business_phone' => [
                    'default_entrypoint' => [
                        'flow_id' => 'missing-flow',
                    ],
                ],
            ],
        ]);

        $service = app(TenantEntrypointProvisioningService::class);
        $tenant = $service->provision($tenant);

        $tenant->refresh();

        $this->assertSame((string) $schedule->id, (string) $tenant->default_schedule_id);
        $this->assertSame(0, ScheduleRule::query()->where('schedule_id', $schedule->id)->count());
        $this->assertSame(1, $tenant->flows()->count());
        $this->assertSame(1, $tenant->dids()->where('description', 'Default Business Phone Entrypoint')->count());
    }
}
