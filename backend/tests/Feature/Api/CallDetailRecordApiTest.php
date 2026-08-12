<?php

namespace Tests\Feature\Api;

use App\Models\CallDetailRecord;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallDetailRecordApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);

        Permission::updateOrCreate(['slug' => 'cdrs.view'], ['module' => 'core']);
        $this->user->grantPermissions(['cdrs.view']);
    }

    /**
     * CDRs are sensitive — who called whom, when — so an ungranted user must be
     * denied rather than implicitly allowed.
     */
    public function test_user_without_permission_cannot_list_cdrs(): void
    {
        $unprivileged = User::factory()->create(['organization_id' => $this->organization->id]);
        CallDetailRecord::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($unprivileged, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/cdrs")
            ->assertForbidden();
    }

    public function test_can_list_cdrs_for_a_organization(): void
    {
        CallDetailRecord::factory()->count(3)->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/cdrs");

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_show_a_cdr(): void
    {
        $cdr = CallDetailRecord::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/cdrs/{$cdr->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['uuid' => $cdr->uuid]);
    }

    public function test_cdrs_are_read_only_no_create(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/organizations/{$this->organization->id}/cdrs", [
                'uuid' => 'test-uuid',
                'caller_id_number' => '1001',
                'destination_number' => '1002',
            ]);

        $response->assertStatus(405);
    }

    public function test_cdrs_are_read_only_no_delete(): void
    {
        $cdr = CallDetailRecord::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/organizations/{$this->organization->id}/cdrs/{$cdr->id}");

        $response->assertStatus(405);
    }

    public function test_can_filter_cdrs_by_direction(): void
    {
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'direction' => 'inbound',
        ]);
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'direction' => 'outbound',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/cdrs?direction=inbound");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_can_filter_cdrs_by_uuid(): void
    {
        $cdr = CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'uuid' => 'unique-call-uuid-123',
        ]);
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'uuid' => 'other-uuid',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/cdrs?uuid=unique-call-uuid-123");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['uuid' => 'unique-call-uuid-123']);
    }

    public function test_can_filter_cdrs_by_caller_id_number(): void
    {
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'caller_id_number' => '+15551234567',
        ]);
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'caller_id_number' => '+15559999999',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/cdrs?caller_id_number=".urlencode('+15551234567'));

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_can_filter_cdrs_by_destination_number(): void
    {
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'destination_number' => '1001',
        ]);
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'destination_number' => '1002',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/cdrs?destination_number=1001");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_can_filter_cdrs_by_hangup_cause(): void
    {
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'hangup_cause' => 'NORMAL_CLEARING',
        ]);
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'hangup_cause' => 'USER_BUSY',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/cdrs?hangup_cause=USER_BUSY");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_returns_404_for_wrong_organization(): void
    {
        $otherOrganization = Organization::factory()->create();
        $cdr = CallDetailRecord::factory()->create(['organization_id' => $otherOrganization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/cdrs/{$cdr->id}");

        $response->assertStatus(403);
    }

    public function test_cdrs_are_ordered_by_start_stamp_desc(): void
    {
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'start_stamp' => now()->subMinutes(10),
            'caller_id_number' => 'older',
        ]);
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'start_stamp' => now(),
            'caller_id_number' => 'newer',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/cdrs");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('newer', $data[0]['caller_id_number']);
        $this->assertEquals('older', $data[1]['caller_id_number']);
    }
}
