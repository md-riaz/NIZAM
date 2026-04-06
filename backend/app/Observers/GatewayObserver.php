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
        $this->sync($gateway, 'created');
    }

    public function updated(Gateway $gateway): void
    {
        $this->sync($gateway, 'updated');
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

    protected function sync(Gateway $gateway, string $event): void
    {
        try {
            $this->provisioning->sync($gateway);
        } catch (\Throwable $e) {
            Log::error('Failed to sync gateway provisioning', [
                'gateway_id' => $gateway->id,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
