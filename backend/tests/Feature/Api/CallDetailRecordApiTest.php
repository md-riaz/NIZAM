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

    /**
     * A query string can carry an array. Handing one to the date parser or a
     * query binding used to raise a TypeError and answer 500.
     */
    public function test_array_valued_filters_are_ignored_rather_than_crashing(): void
    {
        CallDetailRecord::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/cdrs?date_to[]=2026-01-01&date_from[]=2020-01-01&search[]=x&direction[]=inbound")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_summary_counts_match_the_applied_filters(): void
    {
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'direction' => 'inbound',
            'answer_stamp' => now(),
            'billsec' => 60,
            'hangup_cause' => 'NORMAL_CLEARING',
        ]);
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'direction' => 'inbound',
            'answer_stamp' => null,
            'billsec' => 0,
            'hangup_cause' => 'NO_ANSWER',
        ]);
        // Excluded by the direction filter, so it must not reach the counters.
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'direction' => 'outbound',
            'answer_stamp' => now(),
            'billsec' => 600,
            'hangup_cause' => 'NORMAL_CLEARING',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/cdrs?direction=inbound")
            ->assertOk()
            ->assertJsonPath('meta.summary.total_calls', 2)
            ->assertJsonPath('meta.summary.answered_calls', 1)
            ->assertJsonPath('meta.summary.missed_calls', 1)
            ->assertJsonPath('meta.summary.failed_calls', 0)
            // Whole floats serialize without a decimal part, so these are
            // asserted as the integers the JSON actually carries.
            ->assertJsonPath('meta.summary.acd_seconds', 60)
            ->assertJsonPath('meta.summary.asr', 50);
    }

    public function test_date_to_includes_the_whole_selected_day(): void
    {
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'start_stamp' => '2024-03-04 22:15:00',
            'uuid' => 'late-in-day',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/cdrs?date_from=2024-03-04&date_to=2024-03-04")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', 'late-in-day');
    }

    public function test_recording_data_is_withheld_without_the_recordings_permission(): void
    {
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'recording_path' => '/var/lib/freeswitch/recordings/secret.wav',
        ]);

        // $this->user holds cdrs.view but not recordings.view.
        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/cdrs")
            ->assertOk()
            ->assertJsonMissingPath('data.0.recording_path')
            ->assertJsonMissingPath('data.0.has_recording')
            ->assertJsonMissing(['secret.wav']);
    }

    public function test_recording_data_is_present_with_the_recordings_permission(): void
    {
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'recording_path' => '/var/lib/freeswitch/recordings/allowed.wav',
        ]);

        Permission::updateOrCreate(['slug' => 'recordings.view'], ['module' => 'core']);
        $this->user->grantPermissions(['recordings.view']);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/cdrs")
            ->assertOk()
            ->assertJsonPath('data.0.has_recording', true);
    }

    public function test_returns_404_for_wrong_organization(): void
    {
        $otherOrganization = Organization::factory()->create();
        $cdr = CallDetailRecord::factory()->create(['organization_id' => $otherOrganization->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/cdrs/{$cdr->id}");

        // The scope check runs before authorization, so a record belonging to
        // another organization is simply absent from this URL rather than
        // confirming its existence with a 403.
        $response->assertStatus(404);
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
