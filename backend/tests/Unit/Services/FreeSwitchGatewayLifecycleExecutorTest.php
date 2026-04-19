<?php

namespace Tests\Unit\Services;

use App\Services\Media\FreeSwitchCommandService;
use App\Services\Media\FreeSwitchGatewayLifecycleExecutor;
use App\Services\Media\GatewayLifecycleAction;
use App\Services\Media\GatewayLifecyclePlan;
use Mockery;
use Tests\TestCase;

class FreeSwitchGatewayLifecycleExecutorTest extends TestCase
{
    public function test_start_executes_reload_rescan_then_start(): void
    {
        $freeSwitch = Mockery::mock(FreeSwitchCommandService::class);
        $freeSwitch->shouldReceive('execute')->once()->with('reloadxml', [], false)->andReturn($this->response('reloadxml', []))->ordered();
        $freeSwitch->shouldReceive('execute')->once()->with('sofia', ['profile', 'external', 'rescan'], false)->andReturn($this->response('sofia', ['profile', 'external', 'rescan']))->ordered();
        $freeSwitch->shouldReceive('execute')->once()->with('sofia', ['profile', 'external', 'startgw', 'v_101'], false)->andReturn($this->response('sofia', ['profile', 'external', 'startgw', 'v_101']))->ordered();

        $executor = new FreeSwitchGatewayLifecycleExecutor($freeSwitch);

        $result = $executor->execute(new GatewayLifecyclePlan(
            action: GatewayLifecycleAction::START,
            reason: 'created_registering_gateway',
            profile: 'external',
            shouldStart: true,
            shouldReloadXml: true,
            shouldRescan: true,
        ), 'v_101');

        $this->assertSame(GatewayLifecycleAction::START, $result['action']);
        $this->assertSame('external', $result['profile']);
        $this->assertTrue($result['started']);
        $this->assertFalse($result['stopped']);
        $this->assertCount(3, $result['commands']);
    }

    public function test_restart_executes_reload_kill_rescan_then_start_on_same_profile(): void
    {
        $freeSwitch = Mockery::mock(FreeSwitchCommandService::class);
        $freeSwitch->shouldReceive('execute')->once()->with('reloadxml', [], false)->andReturn($this->response('reloadxml', []))->ordered();
        $freeSwitch->shouldReceive('execute')->once()->with('sofia', ['profile', 'external', 'killgw', 'v_101'], false)->andReturn($this->response('sofia', ['profile', 'external', 'killgw', 'v_101']))->ordered();
        $freeSwitch->shouldReceive('execute')->once()->with('sofia', ['profile', 'external', 'rescan'], false)->andReturn($this->response('sofia', ['profile', 'external', 'rescan']))->ordered();
        $freeSwitch->shouldReceive('execute')->once()->with('sofia', ['profile', 'external', 'startgw', 'v_101'], false)->andReturn($this->response('sofia', ['profile', 'external', 'startgw', 'v_101']))->ordered();

        $executor = new FreeSwitchGatewayLifecycleExecutor($freeSwitch);

        $result = $executor->execute(new GatewayLifecyclePlan(
            action: GatewayLifecycleAction::RESTART,
            reason: 'registration_fields_changed',
            profile: 'external',
            oldProfile: 'external',
            shouldKill: true,
            shouldStart: true,
            shouldReloadXml: true,
            shouldRescan: true,
        ), 'v_101');

        $this->assertTrue($result['started']);
        $this->assertTrue($result['stopped']);
        $this->assertSame('external', $result['old_profile']);
        $this->assertCount(4, $result['commands']);
    }

    public function test_rescan_only_executes_reload_then_rescan(): void
    {
        $freeSwitch = Mockery::mock(FreeSwitchCommandService::class);
        $freeSwitch->shouldReceive('execute')->once()->with('reloadxml', [], false)->andReturn($this->response('reloadxml', []))->ordered();
        $freeSwitch->shouldReceive('execute')->once()->with('sofia', ['profile', 'external', 'rescan'], false)->andReturn($this->response('sofia', ['profile', 'external', 'rescan']))->ordered();

        $executor = new FreeSwitchGatewayLifecycleExecutor($freeSwitch);

        $result = $executor->execute(new GatewayLifecyclePlan(
            action: GatewayLifecycleAction::RESCAN_ONLY,
            reason: 'non_registration_fields_changed',
            profile: 'external',
            shouldReloadXml: true,
            shouldRescan: true,
        ), 'v_101');

        $this->assertFalse($result['started']);
        $this->assertFalse($result['stopped']);
        $this->assertCount(2, $result['commands']);
    }

    public function test_stop_executes_kill_reload_then_rescan(): void
    {
        $freeSwitch = Mockery::mock(FreeSwitchCommandService::class);
        $freeSwitch->shouldReceive('execute')->once()->with('sofia', ['profile', 'external', 'killgw', 'v_101'], false)->andReturn($this->response('sofia', ['profile', 'external', 'killgw', 'v_101']))->ordered();
        $freeSwitch->shouldReceive('execute')->once()->with('reloadxml', [], false)->andReturn($this->response('reloadxml', []))->ordered();
        $freeSwitch->shouldReceive('execute')->once()->with('sofia', ['profile', 'external', 'rescan'], false)->andReturn($this->response('sofia', ['profile', 'external', 'rescan']))->ordered();

        $executor = new FreeSwitchGatewayLifecycleExecutor($freeSwitch);

        $result = $executor->execute(new GatewayLifecyclePlan(
            action: GatewayLifecycleAction::STOP,
            reason: 'gateway_deleted',
            profile: 'external',
            shouldKill: true,
            shouldReloadXml: true,
            shouldRescan: true,
        ), 'v_101');

        $this->assertFalse($result['started']);
        $this->assertTrue($result['stopped']);
        $this->assertCount(3, $result['commands']);
    }

    public function test_profile_move_kills_old_profile_and_starts_new_profile(): void
    {
        $freeSwitch = Mockery::mock(FreeSwitchCommandService::class);
        $freeSwitch->shouldReceive('execute')->once()->with('reloadxml', [], false)->andReturn($this->response('reloadxml', []))->ordered();
        $freeSwitch->shouldReceive('execute')->once()->with('sofia', ['profile', 'external', 'killgw', 'v_101'], false)->andReturn($this->response('sofia', ['profile', 'external', 'killgw', 'v_101']))->ordered();
        $freeSwitch->shouldReceive('execute')->once()->with('sofia', ['profile', 'external-secondary', 'rescan'], false)->andReturn($this->response('sofia', ['profile', 'external-secondary', 'rescan']))->ordered();
        $freeSwitch->shouldReceive('execute')->once()->with('sofia', ['profile', 'external-secondary', 'startgw', 'v_101'], false)->andReturn($this->response('sofia', ['profile', 'external-secondary', 'startgw', 'v_101']))->ordered();

        $executor = new FreeSwitchGatewayLifecycleExecutor($freeSwitch);

        $result = $executor->execute(new GatewayLifecyclePlan(
            action: GatewayLifecycleAction::RESTART,
            reason: 'registration_fields_changed',
            profile: 'external-secondary',
            oldProfile: 'external',
            shouldKill: true,
            shouldStart: true,
            shouldReloadXml: true,
            shouldRescan: true,
        ), 'v_101');

        $this->assertSame('external-secondary', $result['profile']);
        $this->assertSame('external', $result['old_profile']);
        $this->assertTrue($result['started']);
        $this->assertTrue($result['stopped']);
        $this->assertCount(4, $result['commands']);
    }

    public function test_stop_profile_move_rescans_old_and_new_profiles(): void
    {
        $freeSwitch = Mockery::mock(FreeSwitchCommandService::class);
        $freeSwitch->shouldReceive('execute')->once()->with('sofia', ['profile', 'external', 'killgw', 'v_101'], false)->andReturn($this->response('sofia', ['profile', 'external', 'killgw', 'v_101']))->ordered();
        $freeSwitch->shouldReceive('execute')->once()->with('reloadxml', [], false)->andReturn($this->response('reloadxml', []))->ordered();
        $freeSwitch->shouldReceive('execute')->once()->with('sofia', ['profile', 'external', 'rescan'], false)->andReturn($this->response('sofia', ['profile', 'external', 'rescan']))->ordered();
        $freeSwitch->shouldReceive('execute')->once()->with('sofia', ['profile', 'external-secondary', 'rescan'], false)->andReturn($this->response('sofia', ['profile', 'external-secondary', 'rescan']))->ordered();

        $executor = new FreeSwitchGatewayLifecycleExecutor($freeSwitch);

        $result = $executor->execute(new GatewayLifecyclePlan(
            action: GatewayLifecycleAction::STOP,
            reason: 'gateway_profile_removed',
            profile: 'external-secondary',
            oldProfile: 'external',
            shouldKill: true,
            shouldReloadXml: true,
            shouldRescan: true,
        ), 'v_101');

        $this->assertSame('external-secondary', $result['profile']);
        $this->assertSame('external', $result['old_profile']);
        $this->assertFalse($result['started']);
        $this->assertTrue($result['stopped']);
        $this->assertCount(4, $result['commands']);
    }

    protected function response(string $command, array $arguments): array
    {
        return [
            'command' => $command,
            'arguments' => $arguments,
            'executed' => true,
            'response' => 'OK',
        ];
    }
}
