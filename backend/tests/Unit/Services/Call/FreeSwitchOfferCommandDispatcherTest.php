<?php

namespace Tests\Unit\Services\Call;

use App\Models\CallDeliveryAttempt;
use App\Services\Call\DeliveryPlanItem;
use App\Services\Call\EndpointCandidate;
use App\Services\Call\FreeSwitchOfferCommandDispatcher;
use App\Services\Call\OfferCommandResult;
use App\Services\Call\ReachabilityDecision;
use App\Services\Media\FreeSwitchCommandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreeSwitchOfferCommandDispatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_originate_sip_includes_auto_answer_headers_when_enabled(): void
    {
        $commandService = new class extends FreeSwitchCommandService {
            public ?string $capturedCommand = null;

            public function execute(string $command, array $arguments = [], bool $background = false): array
            {
                $this->capturedCommand = $command;

                return [
                    'executed' => true,
                    'response' => '+OK queued',
                ];
            }
        };

        $dispatcher = new FreeSwitchOfferCommandDispatcher($commandService);

        $result = $dispatcher->originateSip($this->makeSipItem(), [
            'call_session_id' => 'session-1',
            'caller_id_name' => 'Front Desk',
            'caller_id_number' => '1000',
            'auto_answer_enabled' => true,
            'auto_answer_call_info' => 'answer-after=0',
            'auto_answer_alert_info' => 'intercom',
        ]);

        $this->assertInstanceOf(OfferCommandResult::class, $result);
        $this->assertTrue($result->executed);
        $this->assertNotNull($commandService->capturedCommand);
        $this->assertStringContainsString('sip_auto_answer=true', $commandService->capturedCommand);
        $this->assertStringContainsString('sip_h_Call-Info=answer-after=0', $commandService->capturedCommand);
        $this->assertStringContainsString('sip_h_Alert-Info=intercom', $commandService->capturedCommand);
        $this->assertStringNotContainsString('sip_h_Answer-Mode=', $commandService->capturedCommand);
    }

    public function test_originate_sip_omits_auto_answer_headers_when_disabled(): void
    {
        $commandService = new class extends FreeSwitchCommandService {
            public ?string $capturedCommand = null;

            public function execute(string $command, array $arguments = [], bool $background = false): array
            {
                $this->capturedCommand = $command;

                return [
                    'executed' => true,
                    'response' => '+OK queued',
                ];
            }
        };

        $dispatcher = new FreeSwitchOfferCommandDispatcher($commandService);

        $dispatcher->originateSip($this->makeSipItem(), [
            'call_session_id' => 'session-1',
            'caller_id_name' => 'Front Desk',
            'caller_id_number' => '1000',
            'auto_answer_enabled' => false,
        ]);

        $this->assertNotNull($commandService->capturedCommand);
        $this->assertStringNotContainsString('sip_auto_answer=true', $commandService->capturedCommand);
        $this->assertStringNotContainsString('sip_h_Call-Info=', $commandService->capturedCommand);
        $this->assertStringNotContainsString('sip_h_Alert-Info=', $commandService->capturedCommand);
    }

    private function makeSipItem(): DeliveryPlanItem
    {
        return new DeliveryPlanItem(
            candidate: new EndpointCandidate(
                endpointBindingId: 'binding-1',
                ownerType: 'extension',
                ownerId: 'ext-1',
                candidateType: 'desk_phone',
                sipAor: 'user/1001@example.com',
                pushCapable: false,
                allowLateJoinAfterPush: false,
                forwardNumber: null,
                forwardRequiresConfirm: false,
                priority: 0,
                sourcePath: [['type' => 'extension', 'id' => 'ext-1']],
            ),
            decision: new ReachabilityDecision(
                endpointBindingId: 'binding-1',
                status: ReachabilityDecision::STATUS_ONLINE_SIP,
                canRingNow: true,
                shouldSendPush: false,
                allowLateJoinWindowUntil: null,
                shouldOfferPstn: false,
                decisionReason: 'unit_test',
            ),
            wave: 'immediate_sip',
            attemptType: CallDeliveryAttempt::TYPE_SIP,
            delaySeconds: 0,
            requiresConfirmation: false,
            lateJoinWindowUntil: null,
            metadata: [],
        );
    }
}
