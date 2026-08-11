<?php

namespace Tests\Feature\Api;

use App\Models\Did;
use App\Models\Extension;
use App\Models\Organization;
use App\Models\User;
use App\Services\EslConnectionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Both halves of the presented caller ID must be derived server-side.
 *
 * The number was already constrained to the extension's allowed outbound DIDs,
 * but the display name used to be accepted verbatim from the client — which let
 * any caller who could originate present an arbitrary identity on the PSTN and
 * defeated the point of the allow-list.
 */
class OutboundCallerIdIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_client_supplied_caller_id_name_is_rejected(): void
    {
        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $user = User::factory()->create(['role' => 'admin', 'organization_id' => $organization->id]);
        Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '1001',
            'is_active' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/organizations/{$organization->id}/calls/originate", [
                'extension' => '1001',
                'destination' => '+15550001111',
                'caller_id_name' => 'IRS',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('caller_id_name');
    }

    public function test_caller_id_number_remains_rejected(): void
    {
        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $user = User::factory()->create(['role' => 'admin', 'organization_id' => $organization->id]);
        Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '1001',
            'is_active' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/organizations/{$organization->id}/calls/originate", [
                'extension' => '1001',
                'destination' => '+15550001111',
                'caller_id_number' => '+15559998888',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('caller_id_number');
    }

    public function test_originate_presents_the_extension_configured_name_and_allowed_did(): void
    {
        $organization = Organization::factory()->create(['domain' => 'acme.test']);
        $user = User::factory()->create(['role' => 'admin', 'organization_id' => $organization->id]);

        $did = Did::factory()->create([
            'organization_id' => $organization->id,
            'number' => '+15550100',
            'is_active' => true,
        ]);

        $extension = Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '1001',
            'effective_caller_id_name' => 'Sales Desk',
            'default_outbound_did_id' => $did->id,
            'is_active' => true,
        ]);
        $extension->allowedOutboundDids()->sync([$did->id]);

        $captured = null;
        $esl = Mockery::mock(EslConnectionManager::class);
        $esl->shouldReceive('connect')->once()->andReturn(true);
        $esl->shouldReceive('disconnect')->once();
        $esl->shouldReceive('bgapi')->once()->with(Mockery::capture($captured))->andReturn('+OK');
        $this->app->instance(EslConnectionManager::class, $esl);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/organizations/{$organization->id}/calls/originate", [
                'extension' => '1001',
                'destination' => '+15550001111',
            ])
            ->assertOk();

        $this->assertStringContainsString('origination_caller_id_name=Sales Desk', $captured);
        $this->assertStringContainsString('origination_caller_id_number=', $captured);
        $this->assertStringNotContainsString('IRS', $captured);
    }
}
