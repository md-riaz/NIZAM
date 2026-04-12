<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantDialplanManifest;
use App\Services\Call\CallDeliveryEntrypointService;
use App\Services\Call\CallEventIngestionService;
use App\Services\Call\CallSessionService;
use App\Services\Call\TraceWriter;
use App\Services\DialplanCompiler;
use App\Services\Routing\GatewayResolutionService;
use App\Services\Routing\NumberRoutingService;
use App\Services\SipProfileCompiler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FreeswitchXmlController extends Controller
{
    public function __construct(
        protected DialplanCompiler $compiler,
        protected CallSessionService $callSessionService,
        protected CallDeliveryEntrypointService $callDeliveryEntrypointService,
        protected CallEventIngestionService $callEventIngestionService,
        protected TraceWriter $traceWriter,
        protected GatewayResolutionService $gatewayResolutionService,
        protected NumberRoutingService $numberRoutingService,
        protected SipProfileCompiler $sipProfileCompiler,
    ) {}

    /**
     * Handle mod_xml_curl requests from FreeSWITCH.
     *
     * FreeSWITCH sends POST requests with section, domain, and other params.
     */
    public function handle(Request $request): Response
    {
        $section = $request->input('section', '');

        // mod_xml_curl uses various fields for domain/context depending on the section
        // We prioritize explicit domain fields, then fall back to context if it looks like a domain.
        $domain = $request->input('domain_name',
                  $request->input('domain',
                  $request->input('variable_domain_name',
                  $request->input('Caller-Context', ''))));

        return match ($section) {
            'directory' => $this->handleDirectory($domain),
            'dialplan' => $this->handleDialplan(
                $domain,
                $request->input('Caller-Destination-Number', ''),
                $request->input('Caller-Caller-ID-Number'),
                $request->all(),
            ),
            default => $this->notFoundResponse(),
        };
    }

    protected function handleDirectory(string $domain): Response
    {
        // mod_xml_curl passes 'user' or 'id' for specific user lookups
        $user = request()->input('user', request()->input('id'));
        $xml = $this->compiler->compileDirectory($domain, $user);

        return response($xml, 200, ['Content-Type' => 'text/xml']);
    }

    protected function handleDialplan(string $domain, string $destinationNumber, ?string $callerIdNumber = null, array $requestPayload = []): Response
    {
        $tenant = Tenant::where('domain', $domain)->where('is_active', true)->first();

        if (!$tenant || !$tenant->isOperational()) {
            return $this->notFoundResponse();
        }

        if ($destinationNumber === 'call_delivery_entrypoint') {
            $callUuid = (string) ($requestPayload['Unique-ID'] ?? $requestPayload['Channel-Call-UUID'] ?? $requestPayload['variable_uuid'] ?? '');

            if ($callUuid !== '') {
                $this->callDeliveryEntrypointService->enter($tenant, $callUuid, [
                    'target_type' => (string) ($requestPayload['variable_nizam_delivery_target_type'] ?? $requestPayload['nizam_delivery_target_type'] ?? ''),
                    'target_id' => (string) ($requestPayload['variable_nizam_delivery_target_id'] ?? $requestPayload['nizam_delivery_target_id'] ?? ''),
                    'caller_leg_uuid' => $callUuid,
                    'caller_id_name' => (string) ($requestPayload['Caller-Caller-ID-Name'] ?? $requestPayload['variable_effective_caller_id_name'] ?? ''),
                    'caller_id_number' => (string) ($requestPayload['Caller-Caller-ID-Number'] ?? $requestPayload['variable_effective_caller_id_number'] ?? $callerIdNumber ?? ''),
                    'destination_number' => (string) ($requestPayload['Caller-Destination-Number'] ?? $destinationNumber),
                    'domain' => $domain,
                ]);
            }
        }

        // STEP 7: Serve compiled manifest if available
        $manifest = TenantDialplanManifest::where('tenant_id', $tenant->id)
            ->where('manifest_type', 'inbound_routing')
            ->where('is_active', true)
            ->first();

        if ($manifest) {
            // STEP 9: Emit trace event for compiled manifest usage
            $callUuid = (string) ($requestPayload['Unique-ID'] ?? $requestPayload['Channel-Call-UUID'] ?? $requestPayload['variable_uuid'] ?? '');
            if ($callUuid !== '') {
                $gatewayContext = $this->gatewayResolutionService->resolveFromXmlCurl($tenant, $requestPayload);
                $did = $this->numberRoutingService->resolveInboundDid(
                    $tenant,
                    $destinationNumber,
                    $gatewayContext['gateway'] ?? null,
                );

                $session = $this->callSessionService->getOrCreateInboundSession(
                    $tenant,
                    $callUuid,
                    $did,
                    [
                        'domain' => $domain,
                        'destination_number' => $destinationNumber,
                        'caller_id_number' => $callerIdNumber,
                        'gateway_id' => $gatewayContext['gateway']?->id,
                        'endpoint_type' => DialplanCompiler::inferEndpointType($requestPayload),
                    ],
                );

                $this->traceWriter->write($session, 'compiled.manifest.served', [
                    'domain' => $domain,
                    'destination_number' => $destinationNumber,
                    'caller_id_number' => $callerIdNumber,
                    'gateway_id' => $gatewayContext['gateway']?->id,
                    'did_id' => $did?->id,
                    'manifest_checksum' => $manifest->checksum,
                ]);
            }

            return response($manifest->content, 200, ['Content-Type' => 'text/xml']);
        }

        // FALLBACK: Interpreted runtime (Legacy)
        $callUuid = (string) ($requestPayload['Unique-ID'] ?? $requestPayload['Channel-Call-UUID'] ?? $requestPayload['variable_uuid'] ?? '');

        if ($callUuid !== '') {
            $gatewayContext = $this->gatewayResolutionService->resolveFromXmlCurl($tenant, $requestPayload);
            $did = $this->numberRoutingService->resolveInboundDid(
                $tenant,
                $destinationNumber,
                $gatewayContext['gateway'] ?? null,
            );

            $session = $this->callSessionService->getOrCreateInboundSession(
                $tenant,
                $callUuid,
                $did,
                [
                    'domain' => $domain,
                    'destination_number' => $destinationNumber,
                    'caller_id_number' => $callerIdNumber,
                    'gateway_id' => $gatewayContext['gateway']?->id,
                    'endpoint_type' => DialplanCompiler::inferEndpointType($requestPayload),
                ],
            );

            $this->traceWriter->write($session, 'dialplan.lookup.started', [
                'domain' => $domain,
                'destination_number' => $destinationNumber,
                'caller_id_number' => $callerIdNumber,
                'gateway_id' => $gatewayContext['gateway']?->id,
                'did_id' => $did?->id,
                'destination_type' => $did?->destination_type,
            ]);

            $this->callEventIngestionService->ingest(
                $tenant,
                \App\Models\CallEventLog::EVENT_CALL_CREATED,
                $callUuid,
                [
                    'domain' => $domain,
                    'destination_number' => $destinationNumber,
                    'caller_id_number' => $callerIdNumber,
                    'gateway_id' => $gatewayContext['gateway']?->id,
                    'did_id' => $did?->id,
                ],
                $session,
                'xml_curl'
            );
        }

        $xml = $this->compiler->compileDialplan($domain, $destinationNumber, $callerIdNumber, $requestPayload);

        return response($xml, 200, ['Content-Type' => 'text/xml']);
    }

    protected function notFoundResponse(): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>'."\n"
             .'<document type="freeswitch/xml">'."\n"
             .'  <section name="result">'."\n"
             .'    <result status="not found"/>'."\n"
             .'  </section>'."\n"
             .'</document>';

        return response($xml, 200, ['Content-Type' => 'text/xml']);
    }
}
