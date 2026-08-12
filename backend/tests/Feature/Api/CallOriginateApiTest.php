<?php

namespace Tests\Feature\Api;

use App\Models\Did;
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

    /**
     * Assert an originate command, ignoring the per-call `origination_uuid`.
     *
     * The UUID is generated fresh for every call so it cannot be part of a
     * literal expectation. Matching the rest of the string exactly still pins
     * the caller ID, endpoint, and bridge target.
     */
    private function matchesOriginate(string $command, string $expectedTail): bool
    {
        $prefix = 'originate {origination_uuid=';

        if (! str_starts_with($command, $prefix)) {
            return false;
        }

        $remainder = substr($command, strlen($prefix));
        [$uuid, $tail] = array_pad(explode(',', $remainder, 2), 2, '');

        return $tail === $expectedTail
            && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid) === 1;
    }

    public function test_originate_uses_extension_default_outbound_did_when_gateway_is_not_supplied(): void
    {
        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $user = User::factory()->create(['organization_id' => $organization->id, 'role' => 'admin']);
        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '1001',
            'first_name' => 'John',
            'effective_caller_id_name' => 'John Doe',
            'is_active' => true,
        ]);
        $did = Did::factory()->create([
            'organization_id' => $organization->id,
            'gateway_id' => null,
            'number' => '+15551234567',
            'normalized_number' => '+15551234567',
        ]);
        $extension->allowedOutboundDids()->attach($did->id);
        $extension->update(['default_outbound_did_id' => $did->id]);

        $esl = Mockery::mock();
        $esl->shouldReceive('connect')->once()->andReturnTrue();
        $esl->shouldReceive('bgapi')->once()->withArgs(fn (string $command): bool => $this->matchesOriginate(
            $command,
            'origination_caller_id_name=John Doe,origination_caller_id_number=+15551234567}user/1001@acme.test 2001 XML acme.test'
        ))->andReturn('+OK Job-UUID: test');
        $esl->shouldReceive('disconnect')->once();

        $this->app->instance(\App\Services\EslConnectionManager::class, $esl);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/organizations/{$organization->id}/calls/originate", [
            'extension' => '1001',
            'destination' => '2001',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Call originated.');
    }

    public function test_originate_bridges_via_allowed_gateway_linked_to_selected_did(): void
    {
        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $user = User::factory()->create(['organization_id' => $organization->id, 'role' => 'admin']);
        $gateway = Gateway::factory()->create(['organization_id' => $organization->id]);
        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '1001',
            'first_name' => 'John',
            'effective_caller_id_name' => 'John Doe',
            'default_outbound_gateway_id' => $gateway->id,
            'is_active' => true,
        ]);
        $did = Did::factory()->create([
            'organization_id' => $organization->id,
            'gateway_id' => $gateway->id,
            'number' => '+15551234567',
            'normalized_number' => '+15551234567',
        ]);
        $extension->allowedOutboundDids()->attach($did->id);
        $extension->allowedOutboundGateways()->attach($gateway->id);
        $extension->update(['default_outbound_did_id' => $did->id]);

        $esl = Mockery::mock();
        $esl->shouldReceive('connect')->once()->andReturnTrue();
        $esl->shouldReceive('bgapi')->once()->withArgs(fn (string $command): bool => $this->matchesOriginate(
            $command,
            sprintf(
                'origination_caller_id_name=John Doe,origination_caller_id_number=+15551234567}user/1001@acme.test &bridge(sofia/gateway/v_%s/+15551234567)',
                $gateway->id,
            )
        ))->andReturn('+OK Job-UUID: test');
        $esl->shouldReceive('disconnect')->once();

        $this->app->instance(\App\Services\EslConnectionManager::class, $esl);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/organizations/{$organization->id}/calls/originate", [
            'extension' => '1001',
            'destination' => '+15551234567',
            'did_id' => $did->id,
            'gateway_id' => $gateway->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Call originated.');
    }

    public function test_originate_rejects_direct_caller_id_number_override(): void
    {
        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $user = User::factory()->create(['organization_id' => $organization->id, 'role' => 'admin']);
        Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '1001',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/organizations/{$organization->id}/calls/originate", [
            'extension' => '1001',
            'destination' => '+15551234567',
            'caller_id_number' => '+19999999999',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['caller_id_number']);
    }

    public function test_originate_rejects_gateway_not_allowed_for_extension(): void
    {
        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $user = User::factory()->create(['organization_id' => $organization->id, 'role' => 'admin']);
        $allowedGateway = Gateway::factory()->create(['organization_id' => $organization->id]);
        $blockedGateway = Gateway::factory()->create(['organization_id' => $organization->id]);
        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '1001',
            'first_name' => 'John',
            'is_active' => true,
            'default_outbound_gateway_id' => $allowedGateway->id,
        ]);
        $did = Did::factory()->create([
            'organization_id' => $organization->id,
            'gateway_id' => null,
            'number' => '+15551234567',
            'normalized_number' => '+15551234567',
        ]);
        $extension->allowedOutboundDids()->attach($did->id);
        $extension->allowedOutboundGateways()->attach($allowedGateway->id);
        $extension->update(['default_outbound_did_id' => $did->id]);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/organizations/{$organization->id}/calls/originate", [
            'extension' => '1001',
            'destination' => '+15551234567',
            'gateway_id' => $blockedGateway->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonPath('errors.outbound_policy.0', 'The requested outbound gateway is not allowed for this extension.');
    }
}
