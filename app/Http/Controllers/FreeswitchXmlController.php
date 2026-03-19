<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantDialplanManifest;
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
        // Support both 'domain' and 'domain_name' parameters
        $domain = $request->input('domain_name', $request->input('domain', ''));

        return match ($section) {
            'directory' => $this->handleDirectory($domain),
            'dialplan' => $this->handleDialplan(
                $domain,
                $request->input('Caller-Destination-Number', ''),
                $request->input('Caller-Caller-ID-Number'),
                $request->all(),
            ),
            'configuration' => $this->handleConfiguration($request),
            default => $this->notFoundResponse(),
        };
    }

    protected function handleDirectory(string $domain): Response
    {
        $xml = $this->compiler->compileDirectory($domain);

        return response($xml, 200, ['Content-Type' => 'text/xml']);
    }

    protected function handleConfiguration(Request $request): Response
    {
        $configName = $request->input('key_name', '');
        $profileName = $request->input('profile', 'external');

        // Only handle sofia.conf requests for SIP profiles
        if ($configName !== 'sofia.conf') {
            return $this->notFoundResponse();
        }

        $xml = $this->sipProfileCompiler->compileProfile($profileName);

        return response($xml, 200, ['Content-Type' => 'text/xml']);
    }

    protected function handleDialplan(string $domain, string $destinationNumber, ?string $callerIdNumber = null, array $requestPayload = []): Response
    {
        $tenant = Tenant::where('domain', $domain)->where('is_active', true)->first();

        if (!$tenant || !$tenant->isOperational()) {
            return $this->notFoundResponse();
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
                    $gatewayContext['gateway_registration'] ?? null,
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
                        'gateway_registration_id' => $gatewayContext['gateway_registration']?->id,
                    ],
                );

                $this->traceWriter->write($session, 'compiled.manifest.served', [
                    'domain' => $domain,
                    'destination_number' => $destinationNumber,
                    'caller_id_number' => $callerIdNumber,
                    'gateway_id' => $gatewayContext['gateway']?->id,
                    'gateway_registration_id' => $gatewayContext['gateway_registration']?->id,
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
                $gatewayContext['gateway_registration'] ?? null,
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
                    'gateway_registration_id' => $gatewayContext['gateway_registration']?->id,
                ],
            );

            $this->traceWriter->write($session, 'dialplan.lookup.started', [
                'domain' => $domain,
                'destination_number' => $destinationNumber,
                'caller_id_number' => $callerIdNumber,
                'gateway_id' => $gatewayContext['gateway']?->id,
                'gateway_registration_id' => $gatewayContext['gateway_registration']?->id,
                'did_id' => $did?->id,
                'destination_type' => $did?->destination_type,
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
