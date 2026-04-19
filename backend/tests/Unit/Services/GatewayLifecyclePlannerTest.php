<?php

namespace Tests\Unit\Services;

use App\Models\Gateway;
use App\Services\Media\GatewayLifecycleAction;
use App\Services\Media\GatewayLifecyclePlanner;
use Tests\TestCase;

class GatewayLifecyclePlannerTest extends TestCase
{
    public function test_plan_create_returns_start_for_active_registering_gateway(): void
    {
        $gateway = new Gateway([
            'tenant_id' => 'tenant-test-id',
            'is_active' => true,
            'register' => true,
            'username' => 'acct-1001',
            'password' => 'secret',
            'host' => 'sip.example.com',
            'profile' => 'external',
        ]);

        $planner = new GatewayLifecyclePlanner();

        $plan = $planner->forCreate($gateway);

        $this->assertSame(GatewayLifecycleAction::START, $plan->action);
        $this->assertSame('external', $plan->profile);
        $this->assertTrue($plan->shouldWriteFile);
        $this->assertTrue($plan->shouldStart);
        $this->assertSame('created_registering_gateway', $plan->reason);
        $this->assertSame('registration_started', $plan->outcome);
    }

    public function test_plan_update_returns_restart_when_password_changes(): void
    {
        $gateway = new Gateway([
            'tenant_id' => 'tenant-test-id',
            'profile' => 'external',
            'is_active' => true,
            'register' => true,
            'username' => 'acct-1001',
            'password' => 'new-secret',
            'host' => 'sip.example.com',
        ]);

        $planner = new GatewayLifecyclePlanner();

        $plan = $planner->forUpdate($gateway, [
            'profile' => 'external',
            'is_active' => true,
            'register' => true,
            'username' => 'acct-1001',
            'password' => 'old-secret',
            'host' => 'sip.example.com',
        ]);

        $this->assertSame(GatewayLifecycleAction::RESTART, $plan->action);
        $this->assertTrue($plan->shouldKill);
        $this->assertTrue($plan->shouldStart);
        $this->assertSame('registration_fields_changed', $plan->reason);
        $this->assertSame('registration_restarted', $plan->outcome);
    }

    public function test_plan_update_returns_rescan_only_for_codec_change(): void
    {
        $gateway = new Gateway([
            'tenant_id' => 'tenant-test-id',
            'profile' => 'external',
            'is_active' => true,
            'register' => true,
            'username' => 'acct-1001',
            'password' => 'secret',
            'inbound_codecs' => ['PCMU'],
        ]);

        $planner = new GatewayLifecyclePlanner();

        $plan = $planner->forUpdate($gateway, [
            'profile' => 'external',
            'is_active' => true,
            'register' => true,
            'username' => 'acct-1001',
            'password' => 'secret',
            'inbound_codecs' => ['PCMU', 'PCMA'],
        ]);

        $this->assertSame(GatewayLifecycleAction::RESCAN_ONLY, $plan->action);
        $this->assertFalse($plan->shouldKill);
        $this->assertFalse($plan->shouldStart);
        $this->assertSame('non_registration_fields_changed', $plan->reason);
    }

    public function test_plan_update_returns_stop_when_gateway_becomes_inactive(): void
    {
        $gateway = new Gateway([
            'tenant_id' => 'tenant-test-id',
            'profile' => 'external',
            'is_active' => false,
            'register' => true,
            'username' => 'acct-1001',
            'password' => 'secret',
        ]);

        $planner = new GatewayLifecyclePlanner();

        $plan = $planner->forUpdate($gateway, [
            'profile' => 'external',
            'is_active' => true,
            'register' => true,
            'username' => 'acct-1001',
            'password' => 'secret',
        ]);

        $this->assertSame(GatewayLifecycleAction::STOP, $plan->action);
        $this->assertTrue($plan->shouldKill);
        $this->assertTrue($plan->shouldDeleteFile);
    }

    public function test_plan_update_preserves_old_profile_for_profile_move(): void
    {
        $gateway = new Gateway([
            'tenant_id' => 'tenant-test-id',
            'profile' => 'external-secondary',
            'is_active' => true,
            'register' => true,
            'username' => 'acct-1001',
            'password' => 'secret',
        ]);

        $planner = new GatewayLifecyclePlanner();

        $plan = $planner->forUpdate($gateway, [
            'profile' => 'external',
            'is_active' => true,
            'register' => true,
            'username' => 'acct-1001',
            'password' => 'secret',
        ]);

        $this->assertSame(GatewayLifecycleAction::RESTART, $plan->action);
        $this->assertSame('external', $plan->oldProfile);
        $this->assertSame('external-secondary', $plan->profile);
    }

    public function test_plan_delete_returns_stop_and_remove(): void
    {
        $gateway = new Gateway([
            'tenant_id' => 'tenant-test-id',
            'profile' => 'external',
            'is_active' => true,
            'register' => true,
        ]);

        $planner = new GatewayLifecyclePlanner();

        $plan = $planner->forDelete($gateway);

        $this->assertSame(GatewayLifecycleAction::STOP, $plan->action);
        $this->assertTrue($plan->shouldKill);
        $this->assertTrue($plan->shouldDeleteFile);
    }

    public function test_plan_create_does_not_start_when_registering_gateway_lacks_credentials(): void
    {
        $gateway = new Gateway([
            'tenant_id' => 'tenant-test-id',
            'profile' => 'external',
            'is_active' => true,
            'register' => true,
            'username' => null,
            'password' => null,
            'host' => 'sip.example.com',
        ]);

        $planner = new GatewayLifecyclePlanner();

        $plan = $planner->forCreate($gateway);

        $this->assertSame(GatewayLifecycleAction::RESCAN_ONLY, $plan->action);
        $this->assertTrue($plan->shouldWriteFile);
        $this->assertFalse($plan->shouldStart);
        $this->assertSame('created_registering_gateway', $plan->reason);
        $this->assertSame('registration_not_started_missing_credentials', $plan->outcome);
    }

    public function test_plan_update_stops_registration_but_preserves_active_non_registering_gateway_file(): void
    {
        $gateway = new Gateway([
            'tenant_id' => 'tenant-test-id',
            'profile' => 'external',
            'is_active' => true,
            'register' => false,
            'username' => 'acct-1001',
            'password' => 'secret',
            'host' => 'sip.example.com',
        ]);

        $planner = new GatewayLifecyclePlanner();

        $plan = $planner->forUpdate($gateway, [
            'profile' => 'external',
            'is_active' => true,
            'register' => true,
            'username' => 'acct-1001',
            'password' => 'secret',
            'host' => 'sip.example.com',
        ]);

        $this->assertSame(GatewayLifecycleAction::STOP, $plan->action);
        $this->assertTrue($plan->shouldKill);
        $this->assertTrue($plan->shouldWriteFile);
        $this->assertFalse($plan->shouldDeleteFile);
        $this->assertFalse($plan->shouldStart);
        $this->assertSame('registration_disabled', $plan->reason);
    }
}
