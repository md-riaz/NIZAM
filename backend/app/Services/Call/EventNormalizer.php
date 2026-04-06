<?php

namespace App\Services\Call;

class EventNormalizer
{
    /**
     * Normalize a raw FreeSWITCH event into a domain event.
     * Returns an array with type, call_uuid, domain, and payload, or null if ignored.
     */
    public function normalize(array $rawEvent): ?array
    {
        $eventName = $rawEvent['Event-Name'] ?? '';
        $callUuid = $rawEvent['Unique-ID'] ?? $rawEvent['Channel-Call-UUID'] ?? $rawEvent['variable_uuid'] ?? null;

        if (! $callUuid) {
            return null;
        }

        $normalized = null;

        switch ($eventName) {
            case 'DTMF':
                $normalized = [
                    'type' => 'menu.selection',
                    'payload' => [
                        'digit' => $rawEvent['DTMF-Digit'] ?? '',
                        'duration' => $rawEvent['DTMF-Duration'] ?? '',
                    ],
                ];
                break;

            case 'CHANNEL_ANSWER':
                $normalized = [
                    'type' => 'call.answered',
                    'payload' => [],
                ];
                break;

            case 'CHANNEL_HANGUP_COMPLETE':
                $normalized = [
                    'type' => 'call.hangup',
                    'payload' => [
                        'cause' => $rawEvent['Hangup-Cause'] ?? 'UNKNOWN',
                        'duration' => $rawEvent['variable_duration'] ?? 0,
                        'billsec' => $rawEvent['variable_billsec'] ?? 0,
                    ],
                ];
                break;

            case 'RECORD_STOP':
                $normalized = [
                    'type' => 'recording.stopped',
                    'payload' => [
                        'path' => $rawEvent['Record-File-Path'] ?? '',
                    ],
                ];
                break;
        }

        if (! $normalized) {
            return null;
        }

        $normalized['call_uuid'] = $callUuid;
        // Extract domain to map back to the tenant
        $normalized['domain'] = $rawEvent['variable_domain_name'] 
            ?? $rawEvent['variable_sip_req_host'] 
            ?? $rawEvent['variable_sip_from_host'] 
            ?? null;

        return $normalized;
    }
}
