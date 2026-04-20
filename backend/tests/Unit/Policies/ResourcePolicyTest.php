<?php

namespace Tests\Unit\Policies;

use App\Models\CallDetailRecord;
use App\Models\CallEventLog;
use App\Models\Permission;
use App\Models\Recording;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourcePolicyTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $admin;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        $this->admin = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role' => 'admin',
        ]);
        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role' => 'agent',
        ]);
    }

    public function test_admin_can_view_call_events(): void
    {
        $this->assertTrue($this->admin->can('viewAny', CallEventLog::class));
    }

    public function test_user_without_permissions_can_view_call_events_default_open(): void
    {
        $this->assertTrue($this->user->can('viewAny', CallEventLog::class));
    }

    public function test_user_with_restricted_permissions_cannot_view_call_events(): void
    {
        // Grant some unrelated permission so the user has explicit permissions
        Permission::create(['slug' => 'extensions.view', 'description' => 'View extensions', 'module' => 'core']);
        $this->user->grantPermissions(['extensions.view']);

        $this->assertFalse($this->user->can('viewAny', CallEventLog::class));
    }

    public function test_user_with_call_events_permission_can_view(): void
    {
        Permission::create(['slug' => 'call_events.view', 'description' => 'View call events', 'module' => 'core']);
        Permission::create(['slug' => 'extensions.view', 'description' => 'View extensions', 'module' => 'core']);
        $this->user->grantPermissions(['call_events.view', 'extensions.view']);

        $this->assertTrue($this->user->can('viewAny', CallEventLog::class));
    }

    public function test_admin_can_view_recordings(): void
    {
        $this->assertTrue($this->admin->can('viewAny', Recording::class));
    }

    public function test_user_can_view_own_organization_recording(): void
    {
        $recording = Recording::factory()->create(['organization_id' => $this->organization->id]);

        $this->assertTrue($this->user->can('view', $recording));
    }

    public function test_user_cannot_view_other_organization_recording(): void
    {
        $otherOrganization = Organization::factory()->create();
        $recording = Recording::factory()->create(['organization_id' => $otherOrganization->id]);

        $this->assertFalse($this->user->can('view', $recording));
    }

    public function test_user_can_download_own_organization_recording(): void
    {
        $recording = Recording::factory()->create(['organization_id' => $this->organization->id]);

        $this->assertTrue($this->user->can('download', $recording));
    }

    public function test_user_can_delete_own_organization_recording(): void
    {
        $recording = Recording::factory()->create(['organization_id' => $this->organization->id]);

        $this->assertTrue($this->user->can('delete', $recording));
    }

    public function test_admin_can_view_cdrs(): void
    {
        $this->assertTrue($this->admin->can('viewAny', CallDetailRecord::class));
    }

    public function test_user_can_view_own_organization_cdr(): void
    {
        $cdr = CallDetailRecord::factory()->create(['organization_id' => $this->organization->id]);

        $this->assertTrue($this->user->can('view', $cdr));
    }

    public function test_user_cannot_view_other_organization_cdr(): void
    {
        $otherOrganization = Organization::factory()->create();
        $cdr = CallDetailRecord::factory()->create(['organization_id' => $otherOrganization->id]);

        $this->assertFalse($this->user->can('view', $cdr));
    }
}
