<?php

namespace Tests\Feature\Api;

use App\Models\CallDetailRecord;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallDetailRecordApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->tenant = Tenant::factory()->create();
    }

    public function test_can_list_cdrs_for_a_tenant(): void
    {
        CallDetailRecord::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/cdrs");

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_show_a_cdr(): void
    {
        $cdr = CallDetailRecord::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/cdrs/{$cdr->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['uuid' => $cdr->uuid]);
    }

    public function test_cdrs_are_read_only_no_create(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tenants/{$this->tenant->id}/cdrs", [
                'uuid' => 'test-uuid',
                'caller_id_number' => '1001',
                'destination_number' => '1002',
            ]);

        $response->assertStatus(405);
    }

    public function test_cdrs_are_read_only_no_delete(): void
    {
        $cdr = CallDetailRecord::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/tenants/{$this->tenant->id}/cdrs/{$cdr->id}");

        $response->assertStatus(405);
    }

    public function test_can_filter_cdrs_by_direction(): void
    {
        CallDetailRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'direction' => 'inbound',
        ]);
        CallDetailRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'direction' => 'outbound',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/cdrs?direction=inbound");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_can_filter_cdrs_by_uuid(): void
    {
        $cdr = CallDetailRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uuid' => 'unique-call-uuid-123',
        ]);
        CallDetailRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uuid' => 'other-uuid',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/cdrs?uuid=unique-call-uuid-123");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['uuid' => 'unique-call-uuid-123']);
    }

    public function test_can_filter_cdrs_by_caller_id_number(): void
    {
        CallDetailRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'caller_id_number' => '+15551234567',
        ]);
        CallDetailRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'caller_id_number' => '+15559999999',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/cdrs?caller_id_number=".urlencode('+15551234567'));

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_can_filter_cdrs_by_destination_number(): void
    {
        CallDetailRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'destination_number' => '1001',
        ]);
        CallDetailRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'destination_number' => '1002',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/cdrs?destination_number=1001");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_can_filter_cdrs_by_hangup_cause(): void
    {
        CallDetailRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hangup_cause' => 'NORMAL_CLEARING',
        ]);
        CallDetailRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hangup_cause' => 'USER_BUSY',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/cdrs?hangup_cause=USER_BUSY");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_returns_404_for_wrong_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $cdr = CallDetailRecord::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/cdrs/{$cdr->id}");

        $response->assertStatus(404);
    }

    public function test_cdrs_are_ordered_by_start_stamp_desc(): void
    {
        CallDetailRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'start_stamp' => now()->subMinutes(10),
            'caller_id_number' => 'older',
        ]);
        CallDetailRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'start_stamp' => now(),
            'caller_id_number' => 'newer',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/cdrs");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('newer', $data[0]['caller_id_number']);
        $this->assertEquals('older', $data[1]['caller_id_number']);
    }
}
