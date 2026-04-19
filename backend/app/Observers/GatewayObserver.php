<?php

namespace App\Observers;

use App\Models\Gateway;
use App\Services\Media\GatewayProvisioningService;
use Illuminate\Support\Facades\Log;

class GatewayObserver
{
    public function __construct(
        protected GatewayProvisioningService $provisioning,
    ) {}

    public function created(Gateway $gateway): void
    {
        $this->syncCreated($gateway);
    }

    public function updated(Gateway $gateway): void
    {
        $this->syncUpdated($gateway);
    }

    public function deleted(Gateway $gateway): void
    {
        try {
            $this->provisioning->remove($gateway);
        } catch (\Throwable $e) {
            Log::error('Failed to remove gateway provisioning', [
                'gateway_id' => $gateway->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function syncCreated(Gateway $gateway): void
    {
        try {
            $this->provisioning->syncCreated($gateway);
        } catch (\Throwable $e) {
            Log::error('Failed to sync created gateway provisioning', [
                'gateway_id' => $gateway->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function syncUpdated(Gateway $gateway): void
    {
        try {
            $this->provisioning->syncUpdated($gateway, $gateway->getOriginal());
        } catch (\Throwable $e) {
            Log::error('Failed to sync updated gateway provisioning', [
                'gateway_id' => $gateway->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
