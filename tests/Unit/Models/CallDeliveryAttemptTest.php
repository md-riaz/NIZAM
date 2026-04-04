<?php

namespace Tests\Unit\Models;

use App\Models\CallDeliveryAttempt;
use App\Models\CallSession;
use App\Models\EndpointBinding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallDeliveryAttemptTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_attempt_belongs_to_call_session_and_endpoint_binding(): void
    {
        $callSession = CallSession::factory()->create();
        $endpointBinding = EndpointBinding::factory()->create([
            'tenant_id' => $callSession->tenant_id,
        ]);

        $attempt = CallDeliveryAttempt::factory()
            ->forCallSession($callSession)
            ->forEndpointBinding($endpointBinding)
            ->create();

        $this->assertTrue($attempt->callSession->is($callSession));
        $this->assertTrue($attempt->endpointBinding->is($endpointBinding));
    }

    public function test_call_session_exposes_delivery_attempt_relationships(): void
    {
        $callSession = CallSession::factory()->create();
        $winningBinding = EndpointBinding::factory()->create([
            'tenant_id' => $callSession->tenant_id,
        ]);
        $activeBinding = EndpointBinding::factory()->create([
            'tenant_id' => $callSession->tenant_id,
        ]);
        $terminalBinding = EndpointBinding::factory()->create([
            'tenant_id' => $callSession->tenant_id,
        ]);

        $winner = CallDeliveryAttempt::factory()
            ->forCallSession($callSession)
            ->forEndpointBinding($winningBinding)
            ->won()
            ->create();

        $active = CallDeliveryAttempt::factory()
            ->forCallSession($callSession)
            ->forEndpointBinding($activeBinding)
            ->active()
            ->create();

        CallDeliveryAttempt::factory()
            ->forCallSession($callSession)
            ->forEndpointBinding($terminalBinding)
            ->create([
                'status' => CallDeliveryAttempt::STATUS_FAILED,
                'ended_at' => now(),
            ]);

        $this->assertCount(3, $callSession->deliveryAttempts);
        $this->assertTrue($callSession->winningDeliveryAttempt->is($winner));
        $this->assertSame([$active->id], $callSession->activeDeliveryAttempts->pluck('id')->all());
    }

    public function test_endpoint_binding_exposes_delivery_attempts(): void
    {
        $endpointBinding = EndpointBinding::factory()->create();
        $callSession = CallSession::factory()->create([
            'tenant_id' => $endpointBinding->tenant_id,
        ]);

        CallDeliveryAttempt::factory()
            ->forCallSession($callSession)
            ->forEndpointBinding($endpointBinding)
            ->count(2)
            ->create();

        $this->assertCount(2, $endpointBinding->deliveryAttempts);
    }

    public function test_status_scopes_filter_active_and_winning_attempts(): void
    {
        $callSession = CallSession::factory()->create();
        $binding = EndpointBinding::factory()->create([
            'tenant_id' => $callSession->tenant_id,
        ]);

        $active = CallDeliveryAttempt::factory()
            ->forCallSession($callSession)
            ->forEndpointBinding($binding)
            ->create([
                'status' => CallDeliveryAttempt::STATUS_INITIATED,
            ]);

        $winner = CallDeliveryAttempt::factory()
            ->forCallSession($callSession)
            ->forEndpointBinding($binding)
            ->won()
            ->create();

        CallDeliveryAttempt::factory()
            ->forCallSession($callSession)
            ->forEndpointBinding($binding)
            ->create([
                'status' => CallDeliveryAttempt::STATUS_CANCELLED,
                'ended_at' => now(),
            ]);

        $this->assertSame([$active->id], CallDeliveryAttempt::query()->active()->pluck('id')->all());
        $this->assertSame([$winner->id], CallDeliveryAttempt::query()->winning()->pluck('id')->all());
    }
}
