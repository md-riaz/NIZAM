<?php

namespace Tests\Feature\Api;

use App\Models\Extension;
use App\Models\Gateway;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CallOriginateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_originate_uses_internal_dialplan_when_gateway_is_not_supplied(): void
    {
        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $user = User::factory()->create(['organization_id' => $organization->id, 'role' => 'admin']);
        Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '1001',
            'first_name' => 'John',
            'effective_caller_id_name' => 'John Doe',
            'effective_caller_id_number' => '8801555123456',
            'is_active' => true,
        ]);

        $esl = Mockery::mock();
        $esl->shouldReceive('connect')->once()->andReturnTrue();
        $esl->shouldReceive('bgapi')->once()->with(
            'originate {origination_caller_id_name=John Doe,origination_caller_id_number=8801555123456}user/1001@acme.test 2001 XML acme.test'
        )->andReturn('+OK Job-UUID: test');
        $esl->shouldReceive('disconnect')->once();

        $this->app->instance(\App\Services\EslConnectionManager::class, $esl);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/organizations/{$organization->id}/calls/originate", [
            'extension' => '1001',
            'destination' => '2001',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Call originated.');
    }

    public function test_originate_bridges_via_gateway_when_gateway_id_is_supplied(): void
    {
        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $user = User::factory()->create(['organization_id' => $organization->id, 'role' => 'admin']);
        Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '1001',
            'first_name' => 'John',
            'effective_caller_id_name' => 'John Doe',
            'effective_caller_id_number' => '8801555123456',
            'is_active' => true,
        ]);
        $gateway = Gateway::factory()->create(['organization_id' => $organization->id]);

        $esl = Mockery::mock();
        $esl->shouldReceive('connect')->once()->andReturnTrue();
        $esl->shouldReceive('bgapi')->once()->with(sprintf(
            'originate {origination_caller_id_name=John Doe,origination_caller_id_number=8801555123456}user/1001@acme.test &bridge(sofia/gateway/v_%s/+15551234567)',
            $gateway->id,
        ))->andReturn('+OK Job-UUID: test');
        $esl->shouldReceive('disconnect')->once();

        $this->app->instance(\App\Services\EslConnectionManager::class, $esl);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/organizations/{$organization->id}/calls/originate", [
            'extension' => '1001',
            'destination' => '+15551234567',
            'gateway_id' => $gateway->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Call originated.');
    }

    public function test_originate_rejects_gateway_from_another_organization(): void
    {
        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $otherOrganization = Organization::factory()->create(['domain' => 'other.test']);
        $user = User::factory()->create(['organization_id' => $organization->id, 'role' => 'admin']);
        Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '1001',
            'is_active' => true,
        ]);
        $gateway = Gateway::factory()->create(['organization_id' => $otherOrganization->id]);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/organizations/{$organization->id}/calls/originate", [
            'extension' => '1001',
            'destination' => '+15551234567',
            'gateway_id' => $gateway->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['gateway_id']);
    }
}
