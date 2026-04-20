<?php

namespace App\Services\Flow;

use App\Data\FlowData;
use App\Models\Flow;

class FlowApplicationService
{
    public function __construct(
        protected FlowGraphService $flowGraphService,
        protected FlowPublishService $flowPublishService,
    ) {}

    public function create(string $organizationId, FlowData $data): Flow
    {
        return $this->flowGraphService->createFlowWithVersion($organizationId, $data);
    }

    public function update(Flow $flow, FlowData $data): Flow
    {
        return $this->flowGraphService->updateFlowWithVersion($flow, $data);
    }

    public function publishLatest(Flow $flow): array
    {
        $version = $flow->versions()->latest('version_number')->first();

        if (! $version) {
            return [
                'success' => false,
                'message' => 'No flow version available to publish.',
                'status' => 422,
            ];
        }

        return $this->flowPublishService->publish($version) + ['status' => 200];
    }
}
