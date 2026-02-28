<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Extension;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $user;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'user',
        ]);
    }

    public function test_admin_can_list_audit_logs(): void
    {
        // Create an audit log via the model
        AuditLog::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'action' => 'created',
            'auditable_type' => Extension::class,
            'auditable_id' => 'test-id',
            'old_values' => null,
            'new_values' => ['extension' => '1001'],
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/audit-logs");

        $response->assertStatus(200);
        $response->assertJsonFragment(['action' => 'created']);
    }

    public function test_user_can_list_audit_logs_default_open(): void
    {
        AuditLog::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'action' => 'updated',
            'auditable_type' => Extension::class,
            'auditable_id' => 'test-id',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/audit-logs");

        $response->assertStatus(200);
    }

    public function test_can_filter_audit_logs_by_action(): void
    {
        AuditLog::create([
            'tenant_id' => $this->tenant->id,
            'action' => 'created',
            'auditable_type' => Extension::class,
            'auditable_id' => 'test-1',
        ]);
        AuditLog::create([
            'tenant_id' => $this->tenant->id,
            'action' => 'deleted',
            'auditable_type' => Extension::class,
            'auditable_id' => 'test-2',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/audit-logs?action=created");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('created', $data[0]['action']);
    }

    public function test_can_filter_audit_logs_by_auditable_type(): void
    {
        AuditLog::create([
            'tenant_id' => $this->tenant->id,
            'action' => 'created',
            'auditable_type' => Extension::class,
            'auditable_id' => 'test-1',
        ]);
        AuditLog::create([
            'tenant_id' => $this->tenant->id,
            'action' => 'created',
            'auditable_type' => Tenant::class,
            'auditable_id' => 'test-2',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/audit-logs?auditable_type=".urlencode(Extension::class));

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    public function test_audit_logs_are_tenant_scoped(): void
    {
        $otherTenant = Tenant::factory()->create();
        AuditLog::create([
            'tenant_id' => $otherTenant->id,
            'action' => 'created',
            'auditable_type' => Extension::class,
            'auditable_id' => 'other-tenant',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/audit-logs");

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_can_show_single_audit_log(): void
    {
        $log = AuditLog::create([
            'tenant_id' => $this->tenant->id,
            'action' => 'updated',
            'auditable_type' => Extension::class,
            'auditable_id' => 'test-id',
            'old_values' => ['name' => 'Old'],
            'new_values' => ['name' => 'New'],
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/tenants/{$this->tenant->id}/audit-logs/{$log->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.action', 'updated');
    }

    public function test_audit_log_requires_authentication(): void
    {
        $response = $this->getJson("/api/tenants/{$this->tenant->id}/audit-logs");

        $response->assertStatus(401);
    }
}
