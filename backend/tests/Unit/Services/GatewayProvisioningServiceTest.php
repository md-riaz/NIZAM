<?php

namespace Tests\Unit\Services;

use App\Models\Gateway;
use App\Services\Media\FreeSwitchCommandService;
use App\Services\Media\FreeSwitchGatewayLifecycleExecutor;
use App\Services\Media\GatewayLifecycleAction;
use App\Services\Media\GatewayLifecyclePlan;
use App\Services\Media\GatewayLifecyclePlanner;
use App\Services\Media\GatewayProvisioningService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GatewayProvisioningServiceTest extends TestCase
{
    protected string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/gateway-provisioning');
        File::deleteDirectory($this->directory);
        File::ensureDirectoryExists($this->directory);
        config()->set('telephony.gateway_provisioning.external_directory', $this->directory);
    }

    public function test_sync_updated_uses_restart_plan_for_registration_field_change(): void
    {
        $gateway = new Gateway([
            'id' => 'gateway-101',
            'tenant_id' => 'tenant-test-id',
            'is_active' => true,
            'register' => true,
            'username' => 'acct-1001',
            'password' => 'new-secret',
            'profile' => 'external',
        ]);

        $freeSwitch = new class extends FreeSwitchCommandService
        {
            public function execute(string $command, array $arguments = [], bool $background = false): array
            {
                return ['executed' => true];
            }
        };

        $planner = new class extends GatewayLifecyclePlanner
        {
            public function forUpdate(Gateway $gateway, array $original): GatewayLifecyclePlan
            {
                return new GatewayLifecyclePlan(
                    action: GatewayLifecycleAction::RESTART,
                    reason: 'registration_fields_changed',
                    profile: 'external',
                    outcome: 'registration_restarted',
                    oldProfile: 'external',
                    shouldWriteFile: true,
                    shouldKill: true,
                    shouldStart: true,
                    shouldReloadXml: true,
                    shouldRescan: true,
                );
            }
        };

        $executor = new class($freeSwitch) extends FreeSwitchGatewayLifecycleExecutor
        {
            public array $calls = [];

            public function __construct(FreeSwitchCommandService $freeSwitch)
            {
                parent::__construct($freeSwitch);
            }

            public function execute(GatewayLifecyclePlan $plan, string $gatewayIdentifier): array
            {
                $this->calls[] = [$plan->action, $gatewayIdentifier];

                return ['action' => $plan->action];
            }
        };

        $service = new GatewayProvisioningService($freeSwitch, $planner, $executor);
        $summary = $service->syncUpdated($gateway, ['password' => 'old-secret', 'profile' => 'external']);

        $this->assertFileExists($this->directory.'/v_'.$gateway->id.'.xml');
        $this->assertSame(GatewayLifecycleAction::RESTART, $summary['action']);
        $this->assertSame('registration_restarted', $summary['outcome']);
        $this->assertSame([['restart', 'v_'.$gateway->id]], $executor->calls);
    }

    public function test_remove_uses_delete_plan_and_deletes_gateway_xml_file(): void
    {
        $gateway = new Gateway(['id' => 'gateway-102', 'tenant_id' => 'tenant-test-id', 'is_active' => true]);

        $freeSwitch = new class extends FreeSwitchCommandService
        {
            public function execute(string $command, array $arguments = [], bool $background = false): array
            {
                return ['executed' => true, 'command' => $command, 'arguments' => $arguments];
            }
        };

        $planner = new GatewayLifecyclePlanner();
        $executor = new class($freeSwitch) extends FreeSwitchGatewayLifecycleExecutor
        {
            public array $calls = [];

            public function __construct(FreeSwitchCommandService $freeSwitch)
            {
                parent::__construct($freeSwitch);
            }

            public function execute(GatewayLifecyclePlan $plan, string $gatewayIdentifier): array
            {
                $this->calls[] = [$plan->action, $gatewayIdentifier];

                return ['action' => $plan->action];
            }
        };

        $service = new GatewayProvisioningService($freeSwitch, $planner, $executor);
        File::put($this->directory.'/v_'.$gateway->id.'.xml', 'existing');

        $summary = $service->remove($gateway);

        $this->assertFileDoesNotExist($this->directory.'/v_'.$gateway->id.'.xml');
        $this->assertSame('stop', $summary['action']);
        $this->assertSame([['stop', 'v_'.$gateway->id]], $executor->calls);
    }

    public function test_render_only_enables_registration_when_flag_and_credentials_are_present(): void
    {
        $freeSwitch = new class extends FreeSwitchCommandService
        {
            public function execute(string $command, array $arguments = [], bool $background = false): array
            {
                return ['executed' => true];
            }
        };

        $service = new GatewayProvisioningService($freeSwitch);

        $registeringGateway = new Gateway([
            'tenant_id' => 'tenant-test-id',
            'is_active' => true,
            'register' => true,
            'host' => 'sip.example.com',
            'username' => 'acct-1001',
            'password' => 'secret',
            'profile' => 'external',
        ]);
        $disabledGateway = new Gateway([
            'tenant_id' => 'tenant-test-id',
            'is_active' => true,
            'register' => false,
            'host' => 'sip.example.com',
            'username' => 'acct-1001',
            'password' => 'secret',
            'profile' => 'external',
        ]);
        $missingCredentialsGateway = new Gateway([
            'tenant_id' => 'tenant-test-id',
            'is_active' => true,
            'register' => true,
            'host' => 'sip.example.com',
            'username' => 'acct-1001',
            'password' => null,
            'profile' => 'external',
        ]);

        $this->assertStringContainsString('name="register" value="true"', $service->render($registeringGateway));
        $this->assertStringContainsString('name="register" value="false"', $service->render($disabledGateway));
        $this->assertStringContainsString('name="register" value="false"', $service->render($missingCredentialsGateway));
    }

    public function test_sync_updated_returns_missing_credentials_outcome_without_starting_gateway(): void
    {
        $gateway = new Gateway([
            'id' => 'gateway-103',
            'tenant_id' => 'tenant-test-id',
            'is_active' => true,
            'register' => true,
            'host' => 'sip.example.com',
            'username' => 'acct-1001',
            'password' => null,
            'profile' => 'external',
        ]);

        $freeSwitch = new class extends FreeSwitchCommandService
        {
            public function execute(string $command, array $arguments = [], bool $background = false): array
            {
                return ['executed' => true];
            }
        };

        $planner = new GatewayLifecyclePlanner();
        $executor = new class($freeSwitch) extends FreeSwitchGatewayLifecycleExecutor
        {
            public array $calls = [];

            public function __construct(FreeSwitchCommandService $freeSwitch)
            {
                parent::__construct($freeSwitch);
            }

            public function execute(GatewayLifecyclePlan $plan, string $gatewayIdentifier): array
            {
                $this->calls[] = [$plan->action, $plan->outcome, $plan->shouldStart, $gatewayIdentifier];

                return [
                    'action' => $plan->action,
                    'outcome' => $plan->outcome,
                    'started' => $plan->shouldStart,
                ];
            }
        };

        $service = new GatewayProvisioningService($freeSwitch, $planner, $executor);
        $summary = $service->syncUpdated($gateway, [
            'profile' => 'external',
            'is_active' => true,
            'register' => true,
            'host' => 'old-sip.example.com',
            'username' => 'acct-1001',
            'password' => 'old-secret',
        ]);

        $this->assertFileExists($this->directory.'/v_'.$gateway->id.'.xml');
        $this->assertSame(GatewayLifecycleAction::RESTART, $summary['action']);
        $this->assertSame('registration_not_started_missing_credentials', $summary['outcome']);
        $this->assertSame([
            ['restart', 'registration_not_started_missing_credentials', false, 'v_'.$gateway->id],
        ], $executor->calls);
    }

    public function test_reconcile_removes_orphaned_files_and_keeps_database_backed_gateway_files(): void
    {
        $gateway = new Gateway(['id' => 'gateway-104', 'tenant_id' => 'tenant-test-id', 'is_active' => true]);
        File::put($this->directory.'/v_'.$gateway->id.'.xml', 'keep');
        File::put($this->directory.'/v_orphan.xml', 'remove');

        $freeSwitch = new class extends FreeSwitchCommandService
        {
            public array $calls = [];

            public function execute(string $command, array $arguments = [], bool $background = false): array
            {
                $this->calls[] = [$command, $arguments, $background];

                return ['executed' => true, 'command' => $command, 'arguments' => $arguments];
            }
        };

        $service = new GatewayProvisioningService($freeSwitch);
        $summary = $service->reconcile([$gateway]);

        $this->assertContains('v_'.$gateway->id.'.xml', $summary['create_or_update']);
        $this->assertContains('v_orphan.xml', $summary['remove_orphans']);
        $this->assertFileExists($this->directory.'/v_'.$gateway->id.'.xml');
        $this->assertFileDoesNotExist($this->directory.'/v_orphan.xml');
        $this->assertSame([
            ['reloadxml', [], false],
            ['sofia', ['profile', 'external', 'rescan'], false],
        ], $freeSwitch->calls);
    }
}
