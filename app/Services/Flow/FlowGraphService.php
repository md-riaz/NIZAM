<?php

namespace App\Services\Flow;

use App\Models\Flow;
use App\Models\FlowVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FlowGraphService
{
    public function createFlowWithVersion(string $tenantId, array $payload): Flow
    {
        return DB::transaction(function () use ($tenantId, $payload) {
            $flow = Flow::create([
                'tenant_id' => $tenantId,
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
            ]);

            $version = $this->createVersion($flow, $payload['version']['definition'] ?? [], (bool) ($payload['publish'] ?? false));

            if ($version->is_published) {
                $flow->forceFill(['active_version_id' => $version->id])->save();
            }

            return $flow->fresh(['activeVersion', 'versions']);
        });
    }

    public function updateFlowWithVersion(Flow $flow, array $payload): Flow
    {
        return DB::transaction(function () use ($flow, $payload) {
            $flow->fill([
                'name' => $payload['name'] ?? $flow->name,
                'description' => array_key_exists('description', $payload) ? $payload['description'] : $flow->description,
            ])->save();

            if (isset($payload['version']['definition'])) {
                $version = $this->createVersion($flow, $payload['version']['definition'], (bool) ($payload['publish'] ?? false));

                if ($version->is_published) {
                    $flow->forceFill(['active_version_id' => $version->id])->save();
                }
            }

            return $flow->fresh(['activeVersion', 'versions']);
        });
    }

    protected function createVersion(Flow $flow, array $definition, bool $publish): FlowVersion
    {
        $nextVersionNumber = ((int) $flow->versions()->max('version_number')) + 1;
        $nodes = $definition['nodes'] ?? [];
        $edges = $definition['edges'] ?? [];

        $version = $flow->versions()->create([
            'version_number' => $nextVersionNumber,
            'definition_checksum' => md5(json_encode($definition)),
            'status' => $publish ? 'published' : 'draft',
            'is_published' => $publish,
            'definition_json' => $definition,
        ]);

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

        return $version->fresh(['nodes', 'edges']);
    }
}
