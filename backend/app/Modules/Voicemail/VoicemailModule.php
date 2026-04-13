<?php

namespace App\Modules\Voicemail;

use App\Modules\BaseModule;
use App\Models\CallEventLog;

class VoicemailModule extends BaseModule
{
    public function __construct(
        protected ?VoicemailEventService $voicemailEventService = null,
    ) {}

    public function name(): string
    {
        return 'voicemail';
    }

    public function description(): string
    {
        return 'Decoupled voicemail handling with local-first media metadata and voicemail.received module hooks.';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function subscribedEvents(): array
    {
        return [
            CallEventLog::EVENT_VOICEMAIL_RECEIVED,
        ];
    }

    public function config(): array
    {
        return [
            'storage_disk' => 'local',
            'storage_strategy' => 'local-first',
            'event_hooks' => [
                CallEventLog::EVENT_VOICEMAIL_RECEIVED,
            ],
        ];
    }

    public function handleEvent(string $eventType, array $data): void
    {
        if ($eventType !== CallEventLog::EVENT_VOICEMAIL_RECEIVED) {
            return;
        }

        $this->service()->handleReceivedPayload($data);
    }

    protected function service(): VoicemailEventService
    {
        return $this->voicemailEventService ??= app(VoicemailEventService::class);
    }
}
