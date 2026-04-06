<?php

namespace Tests\Unit\Models;

use App\Models\AuditLog;
use App\Models\Extension;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_is_created_when_extension_is_created(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);

        $extension = $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'auditable_type' => Extension::class,
            'auditable_id' => $extension->id,
        ]);
    }

    public function test_audit_log_is_created_when_extension_is_updated(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);

        $extension = $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
            'is_active' => true,
        ]);

        $extension->update(['directory_first_name' => 'Jane']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'updated',
            'auditable_type' => Extension::class,
            'auditable_id' => $extension->id,
        ]);
    }

    public function test_audit_log_is_created_when_extension_is_deleted(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);

        $extension = $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'John',
            'directory_last_name' => 'Doe',
            'is_active' => true,
        ]);

        $extensionId = $extension->id;
        $extension->delete();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'deleted',
            'auditable_type' => Extension::class,
            'auditable_id' => $extensionId,
        ]);
    }

    public function test_audit_log_is_created_for_tenant_operations(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'auditable_type' => Tenant::class,
            'auditable_id' => $tenant->id,
        ]);

        $tenant->update(['name' => 'Updated Name']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'updated',
            'auditable_type' => Tenant::class,
            'auditable_id' => $tenant->id,
        ]);
    }

    public function test_audit_log_uses_uuid_primary_key(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);

        $log = AuditLog::first();
        $this->assertNotNull($log);
        $this->assertIsString($log->id);
        // UUID format check
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $log->id);
    }

    public function test_audit_log_record_stores_old_and_new_values(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Original Name',
            'is_active' => true,
        ]);

        $tenant->update(['name' => 'New Name']);

        $log = AuditLog::where('action', 'updated')
            ->where('auditable_type', Tenant::class)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('Original Name', $log->old_values['name']);
        $this->assertEquals('New Name', $log->new_values['name']);
    }
}
