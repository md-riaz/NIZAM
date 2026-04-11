<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\Extension;
use App\Models\User;
use App\Services\SipRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RegistrationStatusControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_extension_status_includes_user_agent(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'app.local']);
        $admin = User::factory()->create([
            'role' => 'admin',
            'tenant_id' => $tenant->id,
        ]);

        $extension = Extension::factory()->create([
            'tenant_id' => $tenant->id,
            'extension' => '1001',
        ]);

        $registrations = [
            [
                'reg_user' => '1001',
                'sip_auth_realm' => 'app.local',
                'agent' => 'MicroSIP/3.21.6',
                'network_ip' => '172.20.0.1',
                'network_port' => '48116',
            ]
        ];

        $service = Mockery::mock(SipRegistrationService::class);
        $service->shouldReceive('getAllRegistrations')
            ->once()
            ->andReturn($registrations);

        $this->app->instance(SipRegistrationService::class, $service);

        $response = $this->actingAs($admin, 'sanctum')->getJson("/api/v1/tenants/{$tenant->id}/extensions/status/all");

        $response->assertOk()
            ->assertJsonPath('data.0.extension', '1001')
            ->assertJsonPath('data.0.registered', true)
            ->assertJsonPath('data.0.user_agent', 'MicroSIP/3.21.6');
    }
}
