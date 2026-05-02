<?php

namespace App\Services\Routing;

use App\Models\FlowCompiledArtifact;
use App\Models\FlowEdge;
use App\Models\FlowVersion;
use InvalidArgumentException;

class RoutingGraphCompiler
{
    /**
     * @return array<string, mixed>
     */
    public function compile(FlowVersion $flowVersion): array
    {
        $flowVersion->loadMissing(['flow', 'nodes', 'edges']);

        $nodes = $flowVersion->nodes->sortBy('id')->values();
        $edges = $flowVersion->edges
            ->sortBy(fn (FlowEdge $edge) => implode(':', [
                (string) $edge->source_node_id,
                (string) ($edge->condition ?? ''),
                (string) $edge->target_node_id,
                (string) $edge->id,
            ]))
            ->values();

        $nodeIds = $nodes->pluck('id')->map(fn ($id) => (string) $id)->all();
        $nodeIndex = $nodes->keyBy('id');
        $nodeTypesById = $nodes->mapWithKeys(fn ($node) => [(string) $node->id => (string) $node->type])->all();

        $validation = [
            'errors' => [],
            'warnings' => [],
        ];

        $startNodes = $nodes->filter(fn ($node) => $node->type === 'start')->values();

        if ($startNodes->count() !== 1) {
            $validation['errors'][] = [
                'code' => 'invalid_start_node_count',
                'message' => 'Routing graph must contain exactly one start node.',
                'meta' => [
                    'count' => $startNodes->count(),
                ],
            ];
        }

        $adjacency = [];
        $edgeRecords = [];
        $branchTargetsByNode = [];

        foreach ($edges as $edge) {
            $sourceId = (string) $edge->source_node_id;
            $targetId = (string) $edge->target_node_id;
            $branch = $this->normalizeBranchName($edge->condition);

            if (! in_array($sourceId, $nodeIds, true)) {
                $validation['errors'][] = [
                    'code' => 'edge_source_missing',
                    'message' => 'Routing graph edge references a missing source node.',
                    'meta' => [
                        'edge_id' => (string) $edge->id,
                        'source_node_id' => $sourceId,
                    ],
                ];

                continue;
            }

            if (! in_array($targetId, $nodeIds, true)) {
                $validation['errors'][] = [
                    'code' => 'edge_target_missing',
                    'message' => 'Routing graph edge references a missing target node.',
                    'meta' => [
                        'edge_id' => (string) $edge->id,
                        'target_node_id' => $targetId,
                    ],
                ];

                continue;
            }

            if (isset($branchTargetsByNode[$sourceId][$branch])) {
                $validation['errors'][] = [
                    'code' => 'duplicate_branch_transition',
                    'message' => 'Routing graph node contains duplicate branch transitions.',
                    'meta' => [
                        'source_node_id' => $sourceId,
                        'branch' => $branch,
                    ],
                ];
            }

            $branchTargetsByNode[$sourceId][$branch] = $targetId;
            $adjacency[$sourceId][] = $targetId;
            $edgeRecords[] = [
                'source_node_id' => $sourceId,
                'branch' => $branch,
                'target_node_id' => $targetId,
            ];
        }

        foreach ($nodes as $node) {
            $nodeId = (string) $node->id;
            $nodeType = (string) $node->type;
            $branches = $branchTargetsByNode[$nodeId] ?? [];
            $requiredBranches = $this->requiredBranchesForNodeType($nodeType);

            foreach ($requiredBranches as $requiredBranch) {
                if (! array_key_exists($requiredBranch, $branches)) {
                    $validation['errors'][] = [
                        'code' => 'missing_required_branch',
                        'message' => 'Routing graph node is missing a required branch.',
                        'meta' => [
                            'node_id' => $nodeId,
                            'node_type' => $nodeType,
                            'branch' => $requiredBranch,
                        ],
                    ];
                }
            }

            if (in_array($nodeType, ['hangup', 'terminal', 'end_call'], true) && $branches !== []) {
                $validation['warnings'][] = [
                    'code' => 'hangup_has_outgoing_edges',
                    'message' => 'Hangup node has outgoing transitions that will never execute.',
                    'meta' => [
                        'node_id' => $nodeId,
                    ],
                ];
            }
        }

        $entryNodeId = $startNodes->first()?->id;
        $reachableNodeIds = [];

        if ($entryNodeId !== null) {
            $reachableNodeIds = $this->reachableNodeIds((string) $entryNodeId, $adjacency);
        }

        foreach ($nodeIds as $nodeId) {
            if ($entryNodeId !== null && ! in_array($nodeId, $reachableNodeIds, true)) {
                $validation['warnings'][] = [
                    'code' => 'unreachable_node',
                    'message' => 'Routing graph contains an unreachable node.',
                    'meta' => [
                        'node_id' => $nodeId,
                        'node_type' => $nodeTypesById[$nodeId] ?? null,
                    ],
                ];
            }
        }

        $graph = [
            'schema_version' => 1,
            'graph_kind' => 'routing_graph',
            'flow_id' => (string) $flowVersion->flow_id,
            'flow_version_id' => (string) $flowVersion->id,
            'entrypoint' => [
                'node_id' => $entryNodeId !== null ? (string) $entryNodeId : null,
                'extension' => $entryNodeId !== null ? 'flow_'.$flowVersion->flow_id : null,
            ],
            'nodes' => $nodes->map(fn ($node) => [
                'id' => (string) $node->id,
                'type' => (string) $node->type,
                'name' => $node->name,
                'config' => $this->normalizeConfig($node->config_json),
            ])->all(),
            'edges' => $edgeRecords,
            'validation' => [
                'is_valid' => $validation['errors'] === [],
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
            ],
        ];

        $graph['checksum'] = md5(json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));

        return $graph;
    }

    public function store(FlowVersion $flowVersion): FlowCompiledArtifact
    {
        $graph = $this->compile($flowVersion);

        if (! data_get($graph, 'validation.is_valid', false)) {
            throw new InvalidArgumentException('Routing graph compilation failed validation.');
        }

        return FlowCompiledArtifact::updateOrCreate(
            [
                'flow_version_id' => $flowVersion->id,
                'artifact_type' => FlowCompiledArtifact::ARTIFACT_TYPE_ROUTING_GRAPH,
            ],
            [
                'organization_id' => $flowVersion->flow->organization_id,
                'content' => json_encode($graph, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                'checksum' => $graph['checksum'],
            ],
        );
    }

    /**
     * @return list<string>
     */
    protected function requiredBranchesForNodeType(string $nodeType): array
    {
        return match ($nodeType) {
            'start' => ['next'],
            'schedule_check', 'business_hours' => ['open', 'closed', 'holiday'],
            'caller_match', 'number_match' => ['match', 'no_match'],
            'menu' => [],
            'ring_team' => [],
            'voicemail' => [],
            'hangup', 'terminal', 'end_call' => [],
            default => [],
        };
    }

    protected function normalizeBranchName(mixed $branch): string
    {
        $normalized = trim((string) ($branch ?? ''));

        return $normalized === '' ? 'default' : $normalized;
    }

    /**
     * @param  mixed  $config
     * @return array<string, mixed>
     */
    protected function normalizeConfig(mixed $config): array
    {
        if (! is_array($config)) {
            return [];
        }

        ksort($config);

        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $config[$key] = $this->normalizeConfigArray($value);
            }
        }

        return $config;
    }

    /**
     * @param  array<int|string, mixed>  $value
     * @return array<int|string, mixed>
     */
    protected function normalizeConfigArray(array $value): array
    {
        if (array_is_list($value)) {
            return array_map(function ($item) {
                return is_array($item) ? $this->normalizeConfigArray($item) : $item;
            }, $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->normalizeConfigArray($item);
            }
        }

        return $value;
    }

    /**
     * @param  array<string, list<string>>  $adjacency
     * @return list<string>
     */
    protected function reachableNodeIds(string $entryNodeId, array $adjacency): array
    {
        $queue = [$entryNodeId];
        $visited = [];

        while ($queue !== []) {
            $current = array_shift($queue);

            if ($current === null || in_array($current, $visited, true)) {
                continue;
            }

            $visited[] = $current;

            foreach ($adjacency[$current] ?? [] as $targetNodeId) {
                if (! in_array($targetNodeId, $visited, true)) {
                    $queue[] = $targetNodeId;
                }
            }
        }

        sort($visited);

        return $visited;
    }
}
