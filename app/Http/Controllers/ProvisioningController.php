<?php

namespace App\Http\Controllers;

use App\Services\ProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProvisioningController extends Controller
{
    public function __construct(
        protected ProvisioningService $provisioning
    ) {}

    /**
     * Provision a device by MAC address.
     * This endpoint is called by phones during auto-provisioning.
     */
    public function provision(Request $request, string $macAddress): Response
    {
        $profile = $this->provisioning->findByMac($macAddress);

        if (! $profile) {
            return response('Device not found.', 404, ['Content-Type' => 'text/plain']);
        }

        $config = $this->provisioning->renderConfig($profile);

        $contentType = match (strtolower($profile->vendor)) {
            'polycom', 'grandstream' => 'text/xml',
            default => 'text/plain',
        };

        return response($config, 200, ['Content-Type' => $contentType]);
    }
}
