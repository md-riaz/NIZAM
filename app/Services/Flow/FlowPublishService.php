<?php

namespace App\Services\Flow;

use App\Models\Flow;
use App\Models\FlowVersion;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FlowPublishService
{
    public function publish(FlowVersion $flowVersion): FlowVersion
    {
        return DB::transaction(function () use ($flowVersion) {
            $flowVersion->loadMissing('flow');

            if (! $flowVersion->nodes()->exists()) {
                throw new RuntimeException('Cannot publish a flow version with no nodes.');
            }

            $flowVersion->flow->versions()
                ->where('id', '!=', $flowVersion->id)
                ->update([
                    'is_published' => false,
                    'status' => 'archived',
                ]);

            $flowVersion->forceFill([
                'is_published' => true,
                'status' => 'published',
            ])->save();

            $flowVersion->flow->forceFill([
                'active_version_id' => $flowVersion->id,
            ])->save();

            return $flowVersion->fresh();
        });
    }

    public function createDraft(Flow $flow, array $definition): FlowVersion
    {
        $nextVersionNumber = ((int) $flow->versions()->max('version_number')) + 1;

        return $flow->versions()->create([
            'version_number' => $nextVersionNumber,
            'definition_checksum' => md5(json_encode($definition)),
            'status' => 'draft',
            'is_published' => false,
            'definition_json' => $definition,
        ]);
    }
}
