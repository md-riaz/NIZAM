<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\Extension;
use App\Models\SipProfile;
use App\Models\User;
use App\Services\EslConnectionManager;
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

        SipProfile::create([
            'name' => 'internal',
            'description' => 'Internal',
            'is_active' => true,
        ]);

        $xml = <<<'XML'
Content-Type: api/response

<profile>
  <registrations>
    <registration>
      <user>1001@app.local</user>
      <agent>MicroSIP/3.21.6</agent>
      <network-ip>172.20.0.1</network-ip>
      <network-port>48116</network-port>
    </registration>
  </registrations>
</profile>
XML;

        $esl = Mockery::mock(EslConnectionManager::class);
        $esl->shouldReceive('api')
            ->once()
            ->with('sofia xmlstatus profile internal reg')
            ->andReturn($xml);

        $this->app->instance(EslConnectionManager::class, $esl);

        $response = $this->actingAs($admin, 'sanctum')->getJson("/api/v1/tenants/{$tenant->id}/extensions/status/all");

        $response->assertOk()
            ->assertJsonPath('data.0.extension', '1001')
            ->assertJsonPath('data.0.registered', true)
            ->assertJsonPath('data.0.user_agent', 'MicroSIP/3.21.6');
    }
}
