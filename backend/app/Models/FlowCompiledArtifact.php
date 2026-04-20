<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlowCompiledArtifact extends Model
{
    use HasUuids;

    public const ARTIFACT_TYPE_DIALPLAN_XML = 'dialplan_xml';

    public const ARTIFACT_TYPE_ROUTING_GRAPH = 'routing_graph_v1';

    protected $fillable = [
        'organization_id',
        'flow_version_id',
        'artifact_type',
        'content',
        'checksum',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function flowVersion(): BelongsTo
    {
        return $this->belongsTo(FlowVersion::class);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function decodedContent(): ?array
    {
        $decoded = json_decode($this->content, true);

        return is_array($decoded) ? $decoded : null;
    }
}
