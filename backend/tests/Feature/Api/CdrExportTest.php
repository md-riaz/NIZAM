<?php

namespace Tests\Feature\Api;

use App\Models\CallDetailRecord;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CdrExportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_export_returns_csv_with_headers(): void
    {
        CallDetailRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uuid' => 'test-uuid-1',
            'caller_id_name' => 'John',
            'caller_id_number' => '1001',
            'destination_number' => '1002',
            'direction' => 'inbound',
            'hangup_cause' => 'NORMAL_CLEARING',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->get("/api/v1/tenants/{$this->tenant->id}/cdrs/export");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');
        $response->assertHeader('Content-Disposition', 'attachment; filename="cdrs.csv"');

        $content = $response->streamedContent();
        $this->assertStringContainsString('uuid,caller_id_name,caller_id_number,destination_number,direction,start_stamp,answer_stamp,end_stamp,duration,billsec,hangup_cause', $content);
        $this->assertStringContainsString('test-uuid-1', $content);
    }

    public function test_export_respects_direction_filter(): void
    {
        CallDetailRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'direction' => 'inbound',
            'uuid' => 'inbound-uuid',
        ]);
        CallDetailRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'direction' => 'outbound',
            'uuid' => 'outbound-uuid',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->get("/api/v1/tenants/{$this->tenant->id}/cdrs/export?direction=inbound");

        $content = $response->streamedContent();
        $this->assertStringContainsString('inbound-uuid', $content);
        $this->assertStringNotContainsString('outbound-uuid', $content);
    }

    public function test_export_respects_date_filters(): void
    {
        CallDetailRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'start_stamp' => '2024-01-15 10:00:00',
            'uuid' => 'in-range-uuid',
        ]);
        CallDetailRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'start_stamp' => '2024-06-01 10:00:00',
            'uuid' => 'out-range-uuid',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->get("/api/v1/tenants/{$this->tenant->id}/cdrs/export?date_from=2024-01-01&date_to=2024-02-01");

        $content = $response->streamedContent();
        $this->assertStringContainsString('in-range-uuid', $content);
        $this->assertStringNotContainsString('out-range-uuid', $content);
    }

    public function test_export_requires_authentication(): void
    {
        $response = $this->getJson("/api/v1/tenants/{$this->tenant->id}/cdrs/export");

        $response->assertStatus(401);
    }
}
