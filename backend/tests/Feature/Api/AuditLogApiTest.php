<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Extension;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $user;

    private Organization $organization;

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

    public function test_admin_can_list_audit_logs(): void
    {
        // Create an audit log via the model
        AuditLog::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'action' => 'created',
            'auditable_type' => Extension::class,
            'auditable_id' => 'test-id',
            'old_values' => null,
            'new_values' => ['extension' => '1001'],
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/audit-logs");

        $response->assertStatus(200);
        $response->assertJsonFragment(['action' => 'created']);
    }

    /**
     * Permissions are deny-by-default. An agent holding no explicit grant must
     * not be able to read the organization's audit trail — this previously
     * returned 200 because a user with zero grants was allowed everything.
     */
    public function test_agent_without_permission_cannot_list_audit_logs(): void
    {
        AuditLog::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'action' => 'updated',
            'auditable_type' => Extension::class,
            'auditable_id' => 'test-id',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/audit-logs")
            ->assertForbidden();
    }

    public function test_agent_with_granted_permission_can_list_audit_logs(): void
    {
        AuditLog::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'action' => 'updated',
            'auditable_type' => Extension::class,
            'auditable_id' => 'test-id',
        ]);

        Permission::updateOrCreate(['slug' => 'audit_logs.view'], ['module' => 'core']);
        $this->user->grantPermissions(['audit_logs.view']);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/audit-logs")
            ->assertStatus(200);
    }

    public function test_can_filter_audit_logs_by_action(): void
    {
        AuditLog::create([
            'organization_id' => $this->organization->id,
            'action' => 'created',
            'auditable_type' => Extension::class,
            'auditable_id' => 'test-1',
        ]);
        AuditLog::create([
            'organization_id' => $this->organization->id,
            'action' => 'deleted',
            'auditable_type' => Extension::class,
            'auditable_id' => 'test-2',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/audit-logs?action=created");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('created', $data[0]['action']);
    }

    public function test_can_filter_audit_logs_by_auditable_type(): void
    {
        AuditLog::create([
            'organization_id' => $this->organization->id,
            'action' => 'created',
            'auditable_type' => Extension::class,
            'auditable_id' => 'test-1',
        ]);
        AuditLog::create([
            'organization_id' => $this->organization->id,
            'action' => 'created',
            'auditable_type' => Organization::class,
            'auditable_id' => 'test-2',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/audit-logs?auditable_type=".urlencode(Extension::class));

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    public function test_audit_logs_are_organization_scoped(): void
    {
        $otherOrganization = Organization::factory()->create();
        AuditLog::create([
            'organization_id' => $otherOrganization->id,
            'action' => 'created',
            'auditable_type' => Extension::class,
            'auditable_id' => 'other-organization',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/audit-logs");

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_can_show_single_audit_log(): void
    {
        $log = AuditLog::create([
            'organization_id' => $this->organization->id,
            'action' => 'updated',
            'auditable_type' => Extension::class,
            'auditable_id' => 'test-id',
            'old_values' => ['name' => 'Old'],
            'new_values' => ['name' => 'New'],
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/organizations/{$this->organization->id}/audit-logs/{$log->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.action', 'updated');
    }

    public function test_audit_log_requires_authentication(): void
    {
        $response = $this->getJson("/api/v1/organizations/{$this->organization->id}/audit-logs");

        $response->assertStatus(401);
    }
}
