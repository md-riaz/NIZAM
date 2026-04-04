<?php

namespace App\Services\Flow;

use App\Data\FlowData;
use App\Models\Flow;
use App\Models\FlowVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FlowGraphService
{
    public function __construct(
        protected FlowPublishService $flowPublishService,
    ) {}

    public function createFlowWithVersion(string $tenantId, FlowData $data): Flow
    {
        return DB::transaction(function () use ($tenantId, $data) {
            $flow = Flow::create([
                'tenant_id' => $tenantId,
                'name' => $data->name,
                'description' => $data->description,
            ]);

            $version = $this->createVersion($flow, $data->definition, $data->publish);

            if ($version->is_published) {
                $flow->forceFill(['active_version_id' => $version->id])->save();
            }

            return $flow->fresh(['activeVersion', 'versions']);
        });
    }

    public function updateFlowWithVersion(Flow $flow, FlowData $data): Flow
    {
        return DB::transaction(function () use ($flow, $data) {
            $flow->fill([
                'name' => $data->name,
                'description' => $data->description,
            ])->save();

            if ($data->definition !== []) {
                $version = $this->createVersion($flow, $data->definition, $data->publish);

                if ($version->is_published) {
                    $flow->forceFill(['active_version_id' => $version->id])->save();
                }
            }

            return $flow->fresh(['activeVersion', 'versions']);
        });
    }

    protected function createVersion(Flow $flow, array $definition, bool $publish): FlowVersion
    {
        $nodes = $definition['nodes'] ?? [];
        $edges = $definition['edges'] ?? [];

        $version = $this->flowPublishService->createDraft($flow, $definition);

        $nodeIdMap = [];

        foreach ($nodes as $node) {
            $createdNode = $version->nodes()->create([
                'id' => Str::isUuid($node['id'] ?? '') ? $node['id'] : (string) Str::uuid(),
                'type' => $node['type'],
                'name' => $node['name'] ?? $node['type'],
                'config_json' => $node['config'] ?? [],
                'position_x' => $node['position_x'] ?? null,
                'position_y' => $node['position_y'] ?? null,
            ]);

            $nodeIdMap[$node['id']] = $createdNode->id;
        }

        foreach ($edges as $edge) {
            $version->edges()->create([
                'source_node_id' => $nodeIdMap[$edge['source_node_id'] ?? $edge['source'] ?? null] ?? ($edge['source_node_id'] ?? null),
                'target_node_id' => $nodeIdMap[$edge['target_node_id'] ?? $edge['target'] ?? null] ?? ($edge['target_node_id'] ?? null),
                'condition' => $edge['condition'] ?? 'default',
            ]);
        }

        $version = $version->fresh(['nodes', 'edges']);

        if ($publish) {
            $this->flowPublishService->publish($version);
        }

        return $version->fresh(['nodes', 'edges']);
    }
}
