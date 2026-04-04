<?php

namespace App\Services\Call;

use App\Services\Media\FreeSwitchCommandService;
use Illuminate\Support\Str;

class FreeSwitchOfferCommandDispatcher implements OfferCommandDispatcher
{
    public function __construct(
        protected FreeSwitchCommandService $freeSwitchCommandService,
    ) {}

    public function originateSip(DeliveryPlanItem $item, array $context = []): OfferCommandResult
    {
        if (blank($item->candidate->sipAor)) {
            return OfferCommandResult::failure('Missing SIP address of record.');
        }

        $legUuid = (string) Str::uuid();
        $command = $this->buildSipCommand($item, $context, $legUuid);
        $execution = $this->freeSwitchCommandService->execute($command, background: true);

        return $this->resultFromExecution($execution, $legUuid);
    }

    public function originatePstn(DeliveryPlanItem $item, array $context = []): OfferCommandResult
    {
        if (blank($item->candidate->forwardNumber)) {
            return OfferCommandResult::failure('Missing PSTN forward number.');
        }

        $gateway = data_get($context, 'pstn_gateway', config('telephony.call_delivery.default_pstn_gateway'));

        if (blank($gateway)) {
            return OfferCommandResult::failure('Missing PSTN gateway configuration.');
        }

        $legUuid = (string) Str::uuid();
        $command = $this->buildPstnCommand($item, $context, $legUuid, (string) $gateway);
        $execution = $this->freeSwitchCommandService->execute($command, background: true);

        return $this->resultFromExecution($execution, $legUuid);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function buildSipCommand(DeliveryPlanItem $item, array $context, string $legUuid): string
    {
        $callerIdName = $this->escapeValue((string) data_get($context, 'caller_id_name', 'Inbound Call'));
        $callerIdNumber = $this->escapeValue((string) data_get($context, 'caller_id_number', data_get($context, 'caller_number', 'unknown')));

        $variables = [
            'origination_uuid='.$legUuid,
            'origination_caller_id_name='.$callerIdName,
            'origination_caller_id_number='.$callerIdNumber,
            'originate_timeout='.(int) data_get($context, 'sip_originate_timeout', 30),
            'sip_h_X-Nizam-Call-Session-Id='.$this->escapeValue((string) data_get($context, 'call_session_id', '')),
            'sip_h_X-Nizam-Endpoint-Binding-Id='.$this->escapeValue($item->candidate->endpointBindingId),
            'sip_h_X-Nizam-Delivery-Attempt-Type='.$this->escapeValue($item->attemptType),
        ];

        if (filled(data_get($context, 'caller_leg_uuid'))) {
            $variables[] = 'origination_caller_channel_name='.$this->escapeValue((string) data_get($context, 'caller_leg_uuid'));
        }

        return sprintf(
            'originate {%s}%s &park()',
            implode(',', $variables),
            $item->candidate->sipAor,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function buildPstnCommand(DeliveryPlanItem $item, array $context, string $legUuid, string $gateway): string
    {
        $callerIdName = $this->escapeValue((string) data_get($context, 'caller_id_name', 'Inbound Call'));
        $callerIdNumber = $this->escapeValue((string) data_get($context, 'caller_id_number', data_get($context, 'caller_number', 'unknown')));

        $variables = [
            'origination_uuid='.$legUuid,
            'origination_caller_id_name='.$callerIdName,
            'origination_caller_id_number='.$callerIdNumber,
            'originate_timeout='.(int) data_get($context, 'pstn_originate_timeout', 45),
            'sip_h_X-Nizam-Call-Session-Id='.$this->escapeValue((string) data_get($context, 'call_session_id', '')),
            'sip_h_X-Nizam-Endpoint-Binding-Id='.$this->escapeValue($item->candidate->endpointBindingId),
            'sip_h_X-Nizam-Delivery-Attempt-Type='.$this->escapeValue($item->attemptType),
        ];

        if ($item->requiresConfirmation) {
            $variables[] = 'execute_on_answer='.$this->escapeValue((string) data_get($context, 'pstn_confirmation_app', 'playback tone_stream://%(1000,0,640)'));
        }

        return sprintf(
            'originate {%s}sofia/gateway/%s/%s &park()',
            implode(',', $variables),
            $gateway,
            $item->candidate->forwardNumber,
        );
    }

    /**
     * @param  array<string, mixed>  $execution
     */
    protected function resultFromExecution(array $execution, string $legUuid): OfferCommandResult
    {
        if (! ($execution['executed'] ?? false)) {
            return OfferCommandResult::failure(
                (string) ($execution['error'] ?? 'FreeSWITCH originate command failed.'),
                $execution['response'] ?? null,
                ['execution' => $execution],
            );
        }

        return OfferCommandResult::success(
            $execution['response'] ?? null,
            $legUuid,
            ['execution' => $execution],
        );
    }

    protected function escapeValue(string $value): string
    {
        return str_replace([',', '{', '}'], ['\,', '\{', '\}'], $value);
    }
}
