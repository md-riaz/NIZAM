<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\Call\CallEventIngestionService;
use App\Services\Call\CallSessionService;
use App\Services\Call\TraceWriter;
use App\Services\DialplanCompiler;
use App\Services\Flow\FlowRuntimeStarter;
use App\Services\Routing\GatewayResolutionService;
use App\Services\Routing\NumberRoutingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FreeswitchXmlController extends Controller
{
    public function __construct(
        protected DialplanCompiler $compiler,
        protected CallSessionService $callSessionService,
        protected CallEventIngestionService $callEventIngestionService,
        protected TraceWriter $traceWriter,
        protected GatewayResolutionService $gatewayResolutionService,
        protected NumberRoutingService $numberRoutingService,
        protected FlowRuntimeStarter $flowRuntimeStarter,
    ) {}

    /**
     * Handle mod_xml_curl requests from FreeSWITCH.
     *
     * FreeSWITCH sends POST requests with section, domain, and other params.
     */
    public function handle(Request $request): Response
    {
        $section = $request->input('section', '');
        $domain = $request->input('domain', '');

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
        $xml = $this->compiler->compileDirectory($domain);

        return response($xml, 200, ['Content-Type' => 'text/xml']);
    }

    protected function handleDialplan(string $domain, string $destinationNumber, ?string $callerIdNumber = null, array $requestPayload = []): Response
    {
        $tenant = Tenant::where('domain', $domain)->where('is_active', true)->first();

        if ($tenant && $tenant->isOperational()) {
            $callUuid = (string) ($requestPayload['Unique-ID'] ?? $requestPayload['Channel-Call-UUID'] ?? $requestPayload['variable_uuid'] ?? '');

            if ($callUuid !== '') {
                $gatewayContext = $this->gatewayResolutionService->resolveFromXmlCurl($tenant, $requestPayload);
                $did = $this->numberRoutingService->resolveInboundDid(
                    $tenant,
                    $destinationNumber,
                    $gatewayContext['gateway'] ?? null,
                    $gatewayContext['gateway_registration'] ?? null,
                );

                $flow = $did?->destination_type === 'flow'
                    ? $did->destination
                    : null;
                $flowVersion = $flow?->activeVersion;

                $session = $this->callSessionService->getOrCreateInboundSession(
                    $tenant,
                    $callUuid,
                    $did,
                    [
                        'domain' => $domain,
                        'destination_number' => $destinationNumber,
                        'caller_id_number' => $callerIdNumber,
                        'gateway_id' => $gatewayContext['gateway']?->id,
                        'gateway_registration_id' => $gatewayContext['gateway_registration']?->id,
                    ],
                );

                if ($flowVersion) {
                    $session->forceFill([
                        'flow_version_id' => $flowVersion->id,
                    ])->save();
                }

                $this->traceWriter->write($session, 'dialplan.lookup.started', [
                    'domain' => $domain,
                    'destination_number' => $destinationNumber,
                    'caller_id_number' => $callerIdNumber,
                    'gateway_id' => $gatewayContext['gateway']?->id,
                    'gateway_registration_id' => $gatewayContext['gateway_registration']?->id,
                    'did_id' => $did?->id,
                    'destination_type' => $did?->destination_type,
                    'flow_id' => $flow?->id,
                    'flow_version_id' => $flowVersion?->id,
                ]);

                $this->callEventIngestionService->ingest(
                    $tenant,
                    'call.started',
                    $callUuid,
                    [
                        'domain' => $domain,
                        'destination_number' => $destinationNumber,
                        'caller_id_number' => $callerIdNumber,
                        'gateway_id' => $gatewayContext['gateway']?->id,
                        'gateway_registration_id' => $gatewayContext['gateway_registration']?->id,
                        'did_id' => $did?->id,
                        'flow_id' => $flow?->id,
                        'flow_version_id' => $flowVersion?->id,
                    ],
                    $session,
                    'xml_curl'
                );

                if ($flowVersion) {
                    $this->flowRuntimeStarter->start($session, $flowVersion);
                }
            }
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
