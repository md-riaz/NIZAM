<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FlowVersion extends Model
{
    use HasUuids, \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $fillable = [
        'flow_id',
        'version_number',
        'definition_checksum',
        'status',
        'is_published',
        'runtime_mode',
        'definition_json',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'definition_json' => 'array',
        ];
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class);
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(FlowNode::class);
    }

    public function edges(): HasMany
    {
        return $this->hasMany(FlowEdge::class);
    }

    public function routingGraphArtifact(): HasOne
    {
        return $this->hasOne(FlowCompiledArtifact::class)
            ->where('artifact_type', FlowCompiledArtifact::ARTIFACT_TYPE_ROUTING_GRAPH);
    }
}
