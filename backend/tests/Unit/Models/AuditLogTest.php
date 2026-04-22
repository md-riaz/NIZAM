<?php

namespace Tests\Unit\Models;

use App\Models\AuditLog;
use App\Models\Extension;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_is_created_when_extension_is_created(): void
    {
        $organization = Organization::factory()->create(['is_active' => true]);

        $extension = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'auditable_type' => $extension->getMorphClass(),
            'auditable_id' => $extension->id,
        ]);
    }

    public function test_audit_log_is_created_when_extension_is_updated(): void
    {
        $organization = Organization::factory()->create(['is_active' => true]);

        $extension = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_active' => true,
        ]);

        $extension->update(['first_name' => 'Jane']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'updated',
            'auditable_type' => $extension->getMorphClass(),
            'auditable_id' => $extension->id,
        ]);
    }

    public function test_audit_log_is_created_when_extension_is_deleted(): void
    {
        $organization = Organization::factory()->create(['is_active' => true]);

        $extension = $organization->extensions()->create([
            'extension' => '1001',
            'password' => 'secret1234',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_active' => true,
        ]);

        $extensionId = $extension->id;
        $extension->delete();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'deleted',
            'auditable_type' => $extension->getMorphClass(),
            'auditable_id' => $extensionId,
        ]);
    }

    public function test_audit_log_is_created_for_organization_operations(): void
    {
        $organization = Organization::factory()->create(['is_active' => true]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'auditable_type' => Organization::class,
            'auditable_id' => $organization->id,
        ]);

        $organization->update(['name' => 'Updated Name']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'updated',
            'auditable_type' => Organization::class,
            'auditable_id' => $organization->id,
        ]);
    }

    public function test_audit_log_uses_uuid_primary_key(): void
    {
        $organization = Organization::factory()->create(['is_active' => true]);

        $log = AuditLog::first();
        $this->assertNotNull($log);
        $this->assertIsString($log->id);
        // UUID format check
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $log->id);
    }

    public function test_audit_log_record_stores_old_and_new_values(): void
    {
        $organization = Organization::factory()->create([
            'name' => 'Original Name',
            'is_active' => true,
        ]);

        $organization->update(['name' => 'New Name']);

        $log = AuditLog::where('action', 'updated')
            ->where('auditable_type', Organization::class)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('Original Name', $log->old_values['name']);
        $this->assertEquals('New Name', $log->new_values['name']);
    }
}
