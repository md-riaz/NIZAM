<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\User;
use App\Services\Admin\FreeSwitchModuleStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class FreeSwitchModuleStatusApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_view_freeswitch_modules_status(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'organization_id' => null,
        ]);

        $service = Mockery::mock(FreeSwitchModuleStatusService::class);
        $service->shouldReceive('list')
            ->once()
            ->andReturn([
                'ok' => true,
                'data' => new Collection([
                    [
                        'name' => 'mod_sofia',
                        'type' => 'endpoint',
                        'status' => 'running',
                        'supports_start' => false,
                        'supports_stop' => false,
                    ],
                    [
                        'name' => 'mod_xml_curl',
                        'type' => 'xml_handler',
                        'status' => 'not_loaded',
                        'supports_start' => true,
                        'supports_stop' => false,
                    ],
                ]),
                'error' => null,
                'live' => true,
                'source' => 'esl',
            ]);

        $this->app->instance(FreeSwitchModuleStatusService::class, $service);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/freeswitch/modules');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['name', 'type', 'status', 'supports_start', 'supports_stop'],
            ],
        ]);
        $response->assertJsonPath('data.0.name', 'mod_sofia');
        $response->assertJsonPath('data.1.supports_start', true);
    }

    public function test_platform_admin_can_start_a_module(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'organization_id' => null,
        ]);

        $service = Mockery::mock(FreeSwitchModuleStatusService::class);
        $service->shouldReceive('start')
            ->once()
            ->with('mod_xml_curl')
            ->andReturn([
                'ok' => true,
                'action' => 'start',
                'module' => 'mod_xml_curl',
                'response' => '+OK',
                'error' => null,
            ]);

        $this->app->instance(FreeSwitchModuleStatusService::class, $service);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/freeswitch/modules/start', ['module' => 'mod_xml_curl']);

        $response->assertOk();
        $response->assertJsonPath('meta.action', 'start');
        $response->assertJsonPath('meta.module', 'mod_xml_curl');
    }

    public function test_platform_admin_can_stop_an_allowlisted_module(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'organization_id' => null,
        ]);

        $service = Mockery::mock(FreeSwitchModuleStatusService::class);
        $service->shouldReceive('stop')
            ->once()
            ->with('mod_xml_curl')
            ->andReturn([
                'ok' => true,
                'action' => 'stop',
                'module' => 'mod_xml_curl',
                'response' => '+OK',
                'error' => null,
            ]);

        $this->app->instance(FreeSwitchModuleStatusService::class, $service);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/freeswitch/modules/stop', ['module' => 'mod_xml_curl']);

        $response->assertOk();
        $response->assertJsonPath('meta.action', 'stop');
        $response->assertJsonPath('meta.module', 'mod_xml_curl');
    }

    public function test_platform_admin_cannot_stop_a_blocked_module(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'organization_id' => null,
        ]);

        $service = Mockery::mock(FreeSwitchModuleStatusService::class);
        $service->shouldReceive('stop')
            ->once()
            ->with('mod_sofia')
            ->andReturn([
                'ok' => false,
                'action' => 'stop',
                'module' => 'mod_sofia',
                'response' => null,
                'error' => 'This module cannot be stopped from the platform admin UI.',
            ]);

        $this->app->instance(FreeSwitchModuleStatusService::class, $service);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/freeswitch/modules/stop', ['module' => 'mod_sofia']);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'This module cannot be stopped from the platform admin UI.');
    }

    public function test_organization_admin_cannot_view_freeswitch_modules_status(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create([
            'role' => 'admin',
            'organization_id' => $organization->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/freeswitch/modules');

        $response->assertForbidden();
    }

    public function test_platform_admin_receives_503_when_freeswitch_is_unreachable(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'organization_id' => null,
        ]);

        $service = Mockery::mock(FreeSwitchModuleStatusService::class);
        $service->shouldReceive('list')
            ->once()
            ->andReturn([
                'ok' => false,
                'data' => new Collection(),
                'error' => 'Unable to connect to FreeSWITCH ESL.',
                'live' => true,
                'source' => 'esl',
            ]);

        $this->app->instance(FreeSwitchModuleStatusService::class, $service);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/freeswitch/modules');

        $response->assertStatus(503);
        $response->assertJsonPath('meta.source', 'esl');
        $response->assertJsonPath('meta.live', true);
        $response->assertJsonPath('meta.error', 'Unable to connect to FreeSWITCH ESL.');
        $response->assertJsonCount(0, 'data');
    }

    public function test_regular_user_cannot_view_freeswitch_modules_status(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'role' => 'user',
            'organization_id' => $organization->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/freeswitch/modules');

        $response->assertForbidden();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/admin/freeswitch/modules');

        $response->assertUnauthorized();
    }
}
