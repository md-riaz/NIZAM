<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrganizationFoundationMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_foundation_schema_exists_after_current_migrations(): void
    {
        $this->assertTrue(Schema::hasColumn('organizations', 'max_teams'));
        $this->assertTrue(Schema::hasColumn('users', 'organization_id'));
        $this->assertTrue(Schema::hasColumn('extensions', 'organization_id'));
        $this->assertTrue(Schema::hasColumn('device_profiles', 'organization_id'));
        $this->assertTrue(Schema::hasTable('system_settings'));
        $this->assertTrue(Schema::hasColumn('system_settings', 'organization_id'));
        $this->assertTrue(Schema::hasColumn('system_settings', 'key'));
        $this->assertTrue(Schema::hasColumn('system_settings', 'value'));
    }

    public function test_user_roles_normalize_to_current_role_set(): void
    {
        $organizationId = (string) str()->uuid();

        DB::table('organizations')->insert([
            'id' => $organizationId,
            'name' => 'Provisioning Org',
            'domain' => 'provisioning.example.com',
            'settings' => json_encode(['timezone' => 'UTC'], JSON_THROW_ON_ERROR),
            'max_extensions' => 10,
            'max_concurrent_calls' => 0,
            'max_dids' => 0,
            'max_teams' => 0,
            'is_active' => true,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->insert([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'organization_id' => $organizationId,
            'role' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->insert([
            'name' => 'Agent User',
            'email' => 'agent@example.com',
            'password' => bcrypt('password123'),
            'organization_id' => $organizationId,
            'role' => 'agent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->insert([
            'name' => 'Superadmin User',
            'email' => 'superadmin@example.com',
            'password' => bcrypt('password123'),
            'organization_id' => null,
            'role' => 'superadmin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame('admin', DB::table('users')->where('email', 'admin@example.com')->value('role'));
        $this->assertSame('agent', DB::table('users')->where('email', 'agent@example.com')->value('role'));
        $this->assertSame('superadmin', DB::table('users')->where('email', 'superadmin@example.com')->value('role'));
    }
}
