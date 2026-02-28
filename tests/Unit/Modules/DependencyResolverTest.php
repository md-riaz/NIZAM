<?php

namespace Tests\Unit\Modules;

use App\Modules\BaseModule;
use App\Modules\ModuleRegistry;
use RuntimeException;
use Tests\TestCase;

class DependencyResolverTest extends TestCase
{
    public function test_resolves_modules_without_dependencies(): void
    {
        $resolved = ModuleRegistry::resolveDependencies([
            'mod-a' => StubModuleA::class,
            'mod-b' => StubModuleB::class,
        ]);

        $this->assertCount(2, $resolved);
    }

    public function test_resolves_modules_with_dependencies_in_correct_order(): void
    {
        $resolved = ModuleRegistry::resolveDependencies([
            'dependent' => StubDependentModule::class,
            'mod-a' => StubModuleA::class,
        ]);

        $names = array_map(fn ($cls) => app($cls)->name(), $resolved);

        $aPos = array_search('mod-a', $names);
        $depPos = array_search('dependent', $names);

        $this->assertLessThan($depPos, $aPos, 'mod-a should be loaded before dependent');
    }

    public function test_throws_on_missing_dependency(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("depends on unknown module 'missing'");

        ModuleRegistry::resolveDependencies([
            'missing-dep' => StubMissingDepModule::class,
        ]);
    }

    public function test_throws_on_circular_dependency(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        ModuleRegistry::resolveDependencies([
            'circular-a' => StubCircularA::class,
            'circular-b' => StubCircularB::class,
        ]);
    }
}

// Stub module classes for testing
class StubModuleA extends BaseModule
{
    public function name(): string
    {
        return 'mod-a';
    }

    public function description(): string
    {
        return 'Stub A';
    }

    public function version(): string
    {
        return '1.0.0';
    }
}

class StubModuleB extends BaseModule
{
    public function name(): string
    {
        return 'mod-b';
    }

    public function description(): string
    {
        return 'Stub B';
    }

    public function version(): string
    {
        return '1.0.0';
    }
}

class StubDependentModule extends BaseModule
{
    public function name(): string
    {
        return 'dependent';
    }

    public function description(): string
    {
        return 'Depends on mod-a';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function dependencies(): array
    {
        return ['mod-a'];
    }
}

class StubMissingDepModule extends BaseModule
{
    public function name(): string
    {
        return 'missing-dep';
    }

    public function description(): string
    {
        return 'Depends on missing';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function dependencies(): array
    {
        return ['missing'];
    }
}

class StubCircularA extends BaseModule
{
    public function name(): string
    {
        return 'circular-a';
    }

    public function description(): string
    {
        return 'Circular A';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function dependencies(): array
    {
        return ['circular-b'];
    }
}

class StubCircularB extends BaseModule
{
    public function name(): string
    {
        return 'circular-b';
    }

    public function description(): string
    {
        return 'Circular B';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function dependencies(): array
    {
        return ['circular-a'];
    }
}
