<?php

namespace Tests\Unit\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MakeModuleCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Clean up generated module directory (StudlyCase convention)
        $path = base_path('modules/TestModule');
        if (is_dir($path)) {
            $this->recursiveDelete($path);
        }

        parent::tearDown();
    }

    public function test_generates_module_skeleton(): void
    {
        $this->artisan('make:nizam-module', ['name' => 'TestModule'])
            ->assertExitCode(0);

        $basePath = base_path('modules/TestModule');

        $this->assertDirectoryExists($basePath);
        $this->assertFileExists("{$basePath}/app/TestModuleModule.php");
        $this->assertFileExists("{$basePath}/app/Providers/TestModuleServiceProvider.php");
        $this->assertFileExists("{$basePath}/module.json");
        $this->assertFileExists("{$basePath}/config/config.php");
        $this->assertFileExists("{$basePath}/routes/api.php");
        $this->assertFileExists("{$basePath}/composer.json");
        $this->assertFileExists("{$basePath}/README.md");
        $this->assertDirectoryExists("{$basePath}/database/migrations");
        $this->assertDirectoryExists("{$basePath}/tests");
    }

    public function test_module_class_implements_nizam_module(): void
    {
        $this->artisan('make:nizam-module', ['name' => 'TestModule'])
            ->assertExitCode(0);

        $content = file_get_contents(base_path('modules/TestModule/app/TestModuleModule.php'));

        $this->assertStringContainsString('implements NizamModule', $content);
        $this->assertStringContainsString("return 'test-module'", $content);
        $this->assertStringContainsString('public function dialplanContributions', $content);
        $this->assertStringContainsString('public function subscribedEvents', $content);
        $this->assertStringContainsString('public function handleEvent', $content);
        $this->assertStringContainsString('public function permissions', $content);
        $this->assertStringContainsString('public function migrationsPath', $content);
    }

    public function test_module_json_follows_nwidart_convention(): void
    {
        $this->artisan('make:nizam-module', ['name' => 'TestModule'])
            ->assertExitCode(0);

        $json = json_decode(file_get_contents(base_path('modules/TestModule/module.json')), true);

        $this->assertEquals('TestModule', $json['name']);
        $this->assertEquals('test-module', $json['alias']);
        $this->assertContains('Modules\\TestModule\\Providers\\TestModuleServiceProvider', $json['providers']);
    }

    public function test_fails_if_module_already_exists(): void
    {
        $this->artisan('make:nizam-module', ['name' => 'TestModule'])
            ->assertExitCode(0);

        $this->artisan('make:nizam-module', ['name' => 'TestModule'])
            ->assertExitCode(1);
    }

    protected function recursiveDelete(string $dir): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        rmdir($dir);
    }
}
