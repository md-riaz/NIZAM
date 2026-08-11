<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallDeliveryAttempt;
use App\Models\CallSession;
use App\Models\Organization;
use App\Services\Call\OutboundOriginateService;
use App\Services\EslConnectionManager;
use App\Services\Recording\AnsweredRecordingStarter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * API controller for call operations.
 */
class CallController extends Controller
{
    public function __construct(
        protected OutboundOriginateService $outboundOriginateService,
        protected AnsweredRecordingStarter $answeredRecordingStarter,
    ) {}

    /**
     * Originate a call via FreeSWITCH.
     */
    public function originate(Request $request, Organization $organization): JsonResponse
    {
        Gate::authorize('originate');
        $validated = $request->validate([
            'extension' => 'required|string',
            'destination' => 'required|string',
            // Both halves of the presented caller ID are derived server-side.
            // The number comes from the extension's allowed outbound DIDs, and
            // the name from its configured caller-ID name — a client-supplied
            // display name would let any caller present an arbitrary identity
            // ("IRS", a colleague's name) on the PSTN, which the DID allow-list
            // is there to prevent.
            'caller_id_name' => [
                'prohibited',
            ],
            'caller_id_number' => [
                'prohibited',
            ],
            'did_id' => [
                'nullable',
                'uuid',
                function ($attribute, $value, $fail) use ($organization) {
                    if ($value && ! $organization->dids()->where('id', $value)->where('is_active', true)->exists()) {
                        $fail('The selected outbound DID is invalid for this organization.');
                    }
                },
            ],
            'gateway_id' => [
                'nullable',
                'uuid',
                function ($attribute, $value, $fail) use ($organization) {
                    if ($value && ! $organization->gateways()->where('id', $value)->where('is_active', true)->exists()) {
                        $fail('The selected gateway is invalid for this organization.');
                    }
                },
            ],
        ]);

        $extension = $organization->extensions()
            ->where('extension', $validated['extension'])
            ->where('is_active', true)
            ->first();

        if (! $extension) {
            return response()->json(['message' => 'Extension not found or inactive.'], 404);
        }

        $esl = app(EslConnectionManager::class);

        if (! $esl->connect()) {
            return response()->json(['message' => 'Unable to connect to FreeSWITCH.'], 503);
        }

        try {
            $originateString = $this->outboundOriginateService->buildCommand(
                organization: $organization,
                extension: $extension,
                destination: $validated['destination'],
                didId: $validated['did_id'] ?? null,
                gatewayId: $validated['gateway_id'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'outbound_policy' => [$exception->getMessage()],
                ],
            ], 422);
        }

        $response = $esl->bgapi($originateString);
        $esl->disconnect();

        return response()->json([
            'message' => 'Call originated.',
            'response' => $response,
        ]);
    }

    /**
     * Get active channels/calls status for this organization.
     *
     * FreeSWITCH is shared by every tenant, so `show channels` reports the whole
     * switch. Rows are filtered down to channels attributable to this
     * organization — either by a CallSession record or by the dialplan context,
     * which is keyed on the organization domain. Filtering is deliberately
     * inclusive-by-evidence: a channel we cannot attribute is omitted rather
     * than shown, since the alternative leaks other tenants' call metadata.
     */
    public function status(Organization $organization): JsonResponse
    {
        Gate::authorize('viewStatus');
        $esl = app(EslConnectionManager::class);

        if (! $esl->connect()) {
            return response()->json(['message' => 'Unable to connect to FreeSWITCH.'], 503);
        }

        $response = $esl->api('show channels as json');
        $esl->disconnect();

        $channels = json_decode($response ?? '{}', true);
        $rows = is_array($channels['rows'] ?? null) ? $channels['rows'] : [];
        $rows = $this->channelsForOrganization($organization, $rows);

        return response()->json([
            'channels' => $rows,
            'count' => count($rows),
        ]);
    }

    /**
     * Filter raw `show channels` rows down to one organization.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function channelsForOrganization(Organization $organization, array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $uuids = array_values(array_filter(array_map(
            static fn ($row): ?string => is_array($row) ? ($row['uuid'] ?? null) : null,
            $rows,
        )));

        if ($uuids === []) {
            $ownedUuids = [];
        } else {
            $ownedUuids = CallSession::query()
                ->where('organization_id', $organization->id)
                ->whereIn('call_uuid', $uuids)
                ->pluck('call_uuid')
                ->all();

            // Delivered legs have their own channel UUID, so a B-leg belonging
            // to this organization is attributable even though it is not the
            // session's call_uuid.
            $ownedUuids = array_merge($ownedUuids, CallDeliveryAttempt::query()
                ->whereIn('freeswitch_leg_uuid', $uuids)
                ->whereHas('callSession', fn ($query) => $query->where('organization_id', $organization->id))
                ->pluck('freeswitch_leg_uuid')
                ->all());
        }

        $ownedUuids = array_flip($ownedUuids);
        $domain = (string) $organization->domain;

        return array_values(array_filter($rows, static function ($row) use ($ownedUuids, $domain): bool {
            if (! is_array($row)) {
                return false;
            }

            if (isset($row['uuid']) && isset($ownedUuids[$row['uuid']])) {
                return true;
            }

            if ($domain === '') {
                return false;
            }

            if (($row['context'] ?? null) === $domain) {
                return true;
            }

            $presenceId = (string) ($row['presence_id'] ?? '');

            return $presenceId !== '' && str_ends_with($presenceId, '@'.$domain);
        }));
    }

    /**
     * Resolve a channel UUID that this organization is allowed to control.
     *
     * Returns null when the UUID has no CallSession for this organization, which
     * callers translate into a 404. Without this check any authenticated user
     * could hang up, transfer, hold, or start recording another tenant's call
     * simply by naming its UUID.
     */
    protected function organizationOwnsCall(Organization $organization, string $callUuid): bool
    {
        $ownsSession = CallSession::query()
            ->where('organization_id', $organization->id)
            ->where('call_uuid', $callUuid)
            ->exists();

        if ($ownsSession) {
            return true;
        }

        // A call delivered to a SIP or PSTN endpoint has a distinct B-leg
        // channel, and that leg UUID — not the session's call_uuid — is what a
        // supervisor acts on when hanging up or recording the answered leg. It
        // is surfaced to clients as winner.leg_uuid, so it has to be accepted
        // here or legitimate control of an answered call would 404.
        return CallDeliveryAttempt::query()
            ->where('freeswitch_leg_uuid', $callUuid)
            ->whereHas('callSession', fn ($query) => $query->where('organization_id', $organization->id))
            ->exists();
    }

    /**
     * Hangup a call by UUID.
     */
    public function hangup(Request $request, Organization $organization): JsonResponse
    {
        Gate::authorize('callControl');

        $validated = $request->validate([
            'uuid' => 'required|string|max:255',
            'cause' => 'nullable|string|max:100',
        ]);

        if (! $this->organizationOwnsCall($organization, $validated['uuid'])) {
            return response()->json(['message' => 'Call not found for this organization.'], 404);
        }

        $esl = app(EslConnectionManager::class);

        if (! $esl->connect()) {
            return response()->json(['message' => 'Unable to connect to FreeSWITCH.'], 503);
        }

        $cause = $validated['cause'] ?? 'NORMAL_CLEARING';
        $response = $esl->api("uuid_kill {$validated['uuid']} {$cause}");
        $esl->disconnect();

        return response()->json([
            'message' => 'Hangup command sent.',
            'response' => $response,
        ]);
    }

    /**
     * Transfer a call by UUID.
     */
    public function transfer(Request $request, Organization $organization): JsonResponse
    {
        Gate::authorize('callControl');

        $validated = $request->validate([
            'uuid' => 'required|string|max:255',
            'destination' => 'required|string|max:255',
            'leg' => 'nullable|string|in:aleg,bleg,both',
        ]);

        if (! $this->organizationOwnsCall($organization, $validated['uuid'])) {
            return response()->json(['message' => 'Call not found for this organization.'], 404);
        }

        $esl = app(EslConnectionManager::class);

        if (! $esl->connect()) {
            return response()->json(['message' => 'Unable to connect to FreeSWITCH.'], 503);
        }

        $leg = $validated['leg'] ?? '';
        $legFlag = $leg ? "-{$leg} " : '';
        $response = $esl->api("uuid_transfer {$validated['uuid']} {$legFlag}{$validated['destination']} XML {$organization->domain}");
        $esl->disconnect();

        return response()->json([
            'message' => 'Transfer command sent.',
            'response' => $response,
        ]);
    }

    /**
     * Toggle recording on a live call by UUID.
     */
    public function toggleRecording(Request $request, Organization $organization): JsonResponse
    {
        Gate::authorize('callControl');

        $validated = $request->validate([
            'uuid' => 'required|string|max:255',
            'action' => 'required|string|in:start,stop',
        ]);

        if (! $this->organizationOwnsCall($organization, $validated['uuid'])) {
            return response()->json(['message' => 'Call not found for this organization.'], 404);
        }

        $response = $validated['action'] === 'start'
            ? $this->answeredRecordingStarter->startForCall($organization->id, $validated['uuid'])
            : $this->answeredRecordingStarter->stopForCall($organization->id, $validated['uuid']);

        return response()->json([
            'message' => "Recording {$validated['action']} command sent.",
            'response' => $response,
        ]);
    }

    /**
     * Hold or unhold a call by UUID.
     */
    public function hold(Request $request, Organization $organization): JsonResponse
    {
        Gate::authorize('callControl');

        $validated = $request->validate([
            'uuid' => 'required|string|max:255',
            'action' => 'required|string|in:hold,unhold',
        ]);

        if (! $this->organizationOwnsCall($organization, $validated['uuid'])) {
            return response()->json(['message' => 'Call not found for this organization.'], 404);
        }

        $esl = app(EslConnectionManager::class);

        if (! $esl->connect()) {
            return response()->json(['message' => 'Unable to connect to FreeSWITCH.'], 503);
        }

        if ($validated['action'] === 'hold') {
            $response = $esl->api("uuid_hold {$validated['uuid']}");
        } else {
            $response = $esl->api("uuid_hold off {$validated['uuid']}");
        }

        $esl->disconnect();

        return response()->json([
            'message' => "Hold {$validated['action']} command sent.",
            'response' => $response,
        ]);
    }
}
