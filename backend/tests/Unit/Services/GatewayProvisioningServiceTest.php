<?php

namespace Tests\Unit\Services;

use App\Models\Gateway;
use App\Models\Tenant;
use App\Services\Media\FreeSwitchCommandService;
use App\Services\Media\GatewayProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GatewayProvisioningServiceTest extends TestCase
{
    use RefreshDatabase;

    protected string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/gateway-provisioning');
        File::deleteDirectory($this->directory);
        File::ensureDirectoryExists($this->directory);
        config()->set('nizam.gateway_provisioning.external_directory', $this->directory);
    }

    public function test_remove_deletes_gateway_xml_file(): void
    {
        $tenant = Tenant::factory()->create();
        $gateway = Gateway::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

        $freeSwitch = new class extends FreeSwitchCommandService {
            public array $calls = [];
            public function execute(string $command, array $arguments = [], bool $background = false): array
            {
                $this->calls[] = [$command, $arguments, $background];
                return ['executed' => true, 'command' => $command, 'arguments' => $arguments];
            }
        };

        $service = new GatewayProvisioningService($freeSwitch);
        $service->sync($gateway);
        $this->assertFileExists($this->directory.'/v_'.$gateway->id.'.xml');

        $service->remove($gateway);

        $this->assertFileDoesNotExist($this->directory.'/v_'.$gateway->id.'.xml');
    }

    public function test_reconcile_removes_orphaned_files_and_keeps_database_backed_gateway_files(): void
    {
        $tenant = Tenant::factory()->create();
        $gateway = Gateway::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        File::put($this->directory.'/v_'.$gateway->id.'.xml', 'keep');
        File::put($this->directory.'/v_orphan.xml', 'remove');

        $freeSwitch = new class extends FreeSwitchCommandService {
            public function execute(string $command, array $arguments = [], bool $background = false): array
            {
                return ['executed' => true, 'command' => $command, 'arguments' => $arguments];
            }
        };

        $service = new GatewayProvisioningService($freeSwitch);
        $summary = $service->reconcile([$gateway]);

        $this->assertContains('v_'.$gateway->id.'.xml', $summary['create_or_update']);
        $this->assertContains('v_orphan.xml', $summary['remove_orphans']);
        $this->assertFileExists($this->directory.'/v_'.$gateway->id.'.xml');
        $this->assertFileDoesNotExist($this->directory.'/v_orphan.xml');
    }
}
