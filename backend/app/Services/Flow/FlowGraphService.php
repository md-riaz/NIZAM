<?php

namespace App\Services\Flow;

use App\Data\FlowData;
use App\Models\Flow;
use App\Models\FlowVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class FlowGraphService
{
    public function __construct(
        protected FlowPublishService $flowPublishService,
    ) {}

    public function createFlowWithVersion(string $organizationId, FlowData $data): Flow
    {
        return DB::transaction(function () use ($organizationId, $data) {
            $flow = Flow::create([
                'organization_id' => $organizationId,
                'name' => $data->name,
                'description' => $data->description,
            ]);

            $version = $this->createVersion($flow, $data->definition, $data->publish);

            if ($version->is_published) {
                $flow->forceFill(['active_version_id' => $version->id])->save();
            }

            return $flow->fresh([
                'activeVersion.nodes',
                'activeVersion.edges',
                'latestVersion.nodes',
                'latestVersion.edges',
                'versions',
            ]);
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

            return $flow->fresh([
                'activeVersion.nodes',
                'activeVersion.edges',
                'latestVersion.nodes',
                'latestVersion.edges',
                'versions',
            ]);
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
                'config_json' => $this->resolveNodeConfig($flow, $node),
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

    protected function resolveNodeConfig(Flow $flow, array $node): array
    {
        $config = $node['config'] ?? [];

        if (! is_array($config)) {
            return [];
        }

        return match ($node['type'] ?? null) {
            'menu', 'ivr' => $this->resolveMenuConfig($flow, $config),
            default => $config,
        };
    }

    protected function resolveMenuConfig(Flow $flow, array $config): array
    {
        $mediaId = $config['media_id'] ?? $config['prompt_media_id'] ?? null;

        if (! $mediaId) {
            return $config;
        }

        $media = $flow->organization->media()->find($mediaId);

        if (! $media instanceof Media) {
            return $config;
        }

        $config['media_id'] = (string) $media->id;
        $config['prompt_media_id'] = (string) $media->id;
        $config['prompt'] = 'recordings/'.$media->id.'/'.$media->file_name;

        return $config;
    }
}
