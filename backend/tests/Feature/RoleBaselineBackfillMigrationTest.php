<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The switch to deny-by-default permissions would otherwise strip access from
 * every pre-existing agent, since they were relying on the default-open
 * behavior. The backfill migration grants those users their role baseline.
 */
class RoleBaselineBackfillMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function runBackfill(): void
    {
        $migration = require database_path('migrations/2026_05_09_120000_backfill_role_baseline_permissions.php');
        $migration->up();
    }

    private function seedBaselinePermissions(): void
    {
        foreach (User::baselinePermissionsFor('agent') as $slug) {
            Permission::updateOrCreate(['slug' => $slug], ['module' => 'core']);
        }

        Permission::updateOrCreate(['slug' => 'recordings.view'], ['module' => 'core']);
    }

    public function test_it_grants_the_baseline_to_agents_holding_no_permissions(): void
    {
        $organization = Organization::factory()->create();
        $this->seedBaselinePermissions();

        $agent = User::factory()->create(['role' => 'agent', 'organization_id' => $organization->id]);
        $this->assertFalse($agent->hasPermission('extensions.view'));

        $this->runBackfill();

        $granted = $agent->fresh()->permissions->pluck('slug')->sort()->values()->all();
        $expected = collect(User::baselinePermissionsFor('agent'))->sort()->values()->all();

        $this->assertSame($expected, $granted);
        $this->assertTrue($agent->fresh()->hasPermission('extensions.view'));
        $this->assertFalse($agent->fresh()->hasPermission('recordings.view'));
    }

    public function test_it_leaves_agents_with_existing_grants_untouched(): void
    {
        $organization = Organization::factory()->create();
        $this->seedBaselinePermissions();

        $agent = User::factory()->create(['role' => 'agent', 'organization_id' => $organization->id]);
        $agent->grantPermissions(['recordings.view']);

        $this->runBackfill();

        $this->assertSame(
            ['recordings.view'],
            $agent->fresh()->permissions->pluck('slug')->all(),
            'An explicit permission set was already authoritative and must not be widened.',
        );
    }

    public function test_it_is_idempotent(): void
    {
        $organization = Organization::factory()->create();
        $this->seedBaselinePermissions();
        $agent = User::factory()->create(['role' => 'agent', 'organization_id' => $organization->id]);

        $this->runBackfill();
        $afterFirst = $agent->fresh()->permissions->pluck('slug')->sort()->values()->all();

        $this->runBackfill();
        $afterSecond = $agent->fresh()->permissions->pluck('slug')->sort()->values()->all();

        $this->assertSame($afterFirst, $afterSecond);
    }

    public function test_it_tolerates_baseline_slugs_that_are_not_installed(): void
    {
        $organization = Organization::factory()->create();
        // Only one baseline slug exists, e.g. modules contributing the others
        // are disabled in this deployment.
        Permission::updateOrCreate(['slug' => 'extensions.view'], ['module' => 'core']);
        $agent = User::factory()->create(['role' => 'agent', 'organization_id' => $organization->id]);

        $this->runBackfill();

        $this->assertSame(['extensions.view'], $agent->fresh()->permissions->pluck('slug')->all());
    }
}
