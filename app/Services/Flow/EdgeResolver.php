<?php

namespace App\Services\Flow;

class EdgeResolver
{
    public function resolve(array $edges, string $sourceNodeId, ?string $transition): ?array
    {
        foreach ($edges as $edge) {
            if (($edge['source'] ?? $edge['source_node_id'] ?? null) !== $sourceNodeId) {
                continue;
            }

            $condition = $edge['condition'] ?? 'default';

            if ($transition !== null && $condition === $transition) {
                return $edge;
            }
        }

        foreach ($edges as $edge) {
            if (($edge['source'] ?? $edge['source_node_id'] ?? null) !== $sourceNodeId) {
                continue;
            }

            if (($edge['condition'] ?? 'default') === 'default') {
                return $edge;
            }
        }

        return null;
    }
}
