<?php

namespace Tests\Feature\Api;

use App\Models\CallDetailRecord;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CdrExportTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);

        Permission::updateOrCreate(['slug' => 'cdrs.view'], ['module' => 'core']);
        $this->user->grantPermissions(['cdrs.view']);
    }

    public function test_export_denied_without_cdr_permission(): void
    {
        $unprivileged = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($unprivileged, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/cdrs/export")
            ->assertForbidden();
    }

    public function test_export_returns_csv_with_headers(): void
    {
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'uuid' => 'test-uuid-1',
            'caller_id_name' => 'John',
            'caller_id_number' => '1001',
            'destination_number' => '1002',
            'direction' => 'inbound',
            'hangup_cause' => 'NORMAL_CLEARING',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->get("/api/v1/organizations/{$this->organization->id}/cdrs/export");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');
        $this->assertStringStartsWith(
            'attachment; filename="cdrs_export_',
            (string) $response->headers->get('Content-Disposition')
        );
        $this->assertStringEndsWith(
            '.csv"',
            (string) $response->headers->get('Content-Disposition')
        );

        $content = $response->streamedContent();
        $this->assertStringContainsString('uuid,caller_id_name,caller_id_number,destination_number,direction,call_type,start_stamp,answer_stamp,end_stamp,duration,billsec,hangup_cause,quality_score,mos_score,packet_loss,jitter,latency,sip_user_agent,remote_media_ip', $content);
        $this->assertStringContainsString('test-uuid-1', $content);
    }

    public function test_export_respects_direction_filter(): void
    {
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'direction' => 'inbound',
            'uuid' => 'inbound-uuid',
        ]);
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'direction' => 'outbound',
            'uuid' => 'outbound-uuid',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->get("/api/v1/organizations/{$this->organization->id}/cdrs/export?direction=inbound");

        $content = $response->streamedContent();
        $this->assertStringContainsString('inbound-uuid', $content);
        $this->assertStringNotContainsString('outbound-uuid', $content);
    }

    public function test_export_respects_date_filters(): void
    {
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'start_stamp' => '2024-01-15 10:00:00',
            'uuid' => 'in-range-uuid',
        ]);
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'start_stamp' => '2024-06-01 10:00:00',
            'uuid' => 'out-range-uuid',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->get("/api/v1/organizations/{$this->organization->id}/cdrs/export?date_from=2024-01-01&date_to=2024-02-01");

        $content = $response->streamedContent();
        $this->assertStringContainsString('in-range-uuid', $content);
        $this->assertStringNotContainsString('out-range-uuid', $content);
    }

    /**
     * The export used to reimplement its own filter list and silently ignored
     * the `search` box, so exporting a narrowed table produced a CSV of
     * unrelated calls.
     */
    public function test_export_respects_the_search_filter(): void
    {
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'caller_id_number' => '15551230000',
            'uuid' => 'matching-uuid',
        ]);
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'caller_id_number' => '15559990000',
            'uuid' => 'other-uuid',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->get("/api/v1/organizations/{$this->organization->id}/cdrs/export?search=1555123");

        $content = $response->streamedContent();
        $this->assertStringContainsString('matching-uuid', $content);
        $this->assertStringNotContainsString('other-uuid', $content);
    }

    /**
     * A bare `YYYY-MM-DD` upper bound has to cover the whole day; comparing it
     * against a timestamp otherwise drops every call on the selected date.
     */
    public function test_export_date_to_includes_the_whole_selected_day(): void
    {
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'start_stamp' => '2024-01-15 23:45:00',
            'uuid' => 'late-in-day-uuid',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->get("/api/v1/organizations/{$this->organization->id}/cdrs/export?date_from=2024-01-15&date_to=2024-01-15");

        $this->assertStringContainsString('late-in-day-uuid', $response->streamedContent());
    }

    public function test_export_requires_authentication(): void
    {
        $response = $this->getJson("/api/v1/organizations/{$this->organization->id}/cdrs/export");

        $response->assertStatus(401);
    }
}
