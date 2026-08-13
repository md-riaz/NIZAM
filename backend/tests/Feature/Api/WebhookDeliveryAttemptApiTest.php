<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDeliveryAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookDeliveryAttemptApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);

        // Permissions are deny-by-default; delivery attempts are viewed
        // through the webhook's "view" ability.
        Permission::updateOrCreate(['slug' => 'webhooks.view'], ['module' => 'core']);
        $this->user->grantPermissions(['webhooks.view']);
    }

    public function test_can_list_delivery_attempts_for_a_webhook(): void
    {
        $webhook = Webhook::factory()->create(['organization_id' => $this->organization->id]);
        WebhookDeliveryAttempt::factory()->count(3)->create(['webhook_id' => $webhook->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/webhooks/{$webhook->id}/delivery-attempts");

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_delivery_attempts_include_expected_fields(): void
    {
        $webhook = Webhook::factory()->create(['organization_id' => $this->organization->id]);
        WebhookDeliveryAttempt::factory()->create([
            'webhook_id' => $webhook->id,
            'event_type' => 'call.created',
            'response_status' => 200,
            'success' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/webhooks/{$webhook->id}/delivery-attempts");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'event_type' => 'call.created',
            'response_status' => 200,
            'success' => true,
        ]);
    }

    public function test_cannot_view_delivery_attempts_of_other_organizations_webhook(): void
    {
        $otherOrganization = Organization::factory()->create();
        $webhook = Webhook::factory()->create(['organization_id' => $otherOrganization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/webhooks/{$webhook->id}/delivery-attempts");

        $response->assertStatus(404);
    }

    public function test_user_without_permission_cannot_list_delivery_attempts(): void
    {
        $webhook = Webhook::factory()->create(['organization_id' => $this->organization->id]);
        $unprivileged = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($unprivileged, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/webhooks/{$webhook->id}/delivery-attempts")
            ->assertForbidden();
    }
}
