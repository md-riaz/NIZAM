<?php

namespace Tests\Unit;

use App\Console\Commands\SyncPermissionsCommand;
use App\Modules\ModuleRegistry;
use ReflectionClass;
use Tests\TestCase;

/**
 * Guards the permission registry against silent gaps.
 *
 * Permissions are deny-by-default, so a slug that a policy checks but that
 * `nizam:sync-permissions` cannot create is not a cosmetic omission — the row
 * never exists, the grant can never be made, and the feature is unreachable for
 * everyone below admin. `endpoint_bindings.*` sat in exactly that state.
 */
class PermissionRegistryCoverageTest extends TestCase
{
    public function test_every_permission_a_policy_checks_can_be_created_by_sync(): void
    {
        $declared = $this->declaredSlugs();
        $used = $this->slugsCheckedInCode();

        $missing = array_values(array_diff($used, $declared));

        $this->assertSame([], $missing, sprintf(
            'These slugs are checked by hasPermission() but are declared nowhere, so no one can be granted them: %s. '
            .'Add them to SyncPermissionsCommand::$corePermissions or to the owning module\'s permissions().',
            implode(', ', $missing)
        ));
    }

    /**
     * Every slug the app could create: the core list plus every module's, whether
     * that module is currently enabled or not.
     *
     * @return array<int, string>
     */
    private function declaredSlugs(): array
    {
        $core = (new ReflectionClass(SyncPermissionsCommand::class))
            ->newInstanceWithoutConstructor();

        $property = (new ReflectionClass(SyncPermissionsCommand::class))
            ->getProperty('corePermissions');

        $slugs = array_keys($property->getValue($core));

        foreach (app(ModuleRegistry::class)->all() as $module) {
            $slugs = array_merge($slugs, $module->permissions());
        }

        return array_values(array_unique($slugs));
    }

    /**
     * Every slug reached through User::hasPermission() in first-party code.
     *
     * @return array<int, string>
     */
    private function slugsCheckedInCode(): array
    {
        $slugs = [];

        foreach ([base_path('app'), base_path('modules')] as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                preg_match_all(
                    "/hasPermission\(\s*'([a-z_]+\.[a-z_]+)'\s*\)/",
                    (string) file_get_contents($file->getPathname()),
                    $matches
                );

                $slugs = array_merge($slugs, $matches[1]);
            }
        }

        sort($slugs);

        return array_values(array_unique($slugs));
    }
}
