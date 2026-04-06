<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlowEdge extends Model
{
    use HasUuids, \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $fillable = [
        'flow_version_id',
        'source_node_id',
        'target_node_id',
        'condition',
    ];

    public function flowVersion(): BelongsTo
    {
        return $this->belongsTo(FlowVersion::class);
    }

    public function sourceNode(): BelongsTo
    {
        return $this->belongsTo(FlowNode::class, 'source_node_id');
    }

    public function targetNode(): BelongsTo
    {
        return $this->belongsTo(FlowNode::class, 'target_node_id');
    }
}
