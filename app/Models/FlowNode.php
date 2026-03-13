<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlowNode extends Model
{
    use HasUuids;

    protected $fillable = [
        'flow_version_id',
        'type',
        'name',
        'config_json',
        'position_x',
        'position_y',
    ];

    protected function casts(): array
    {
        return [
            'config_json' => 'array',
            'position_x' => 'integer',
            'position_y' => 'integer',
        ];
    }

    public function flowVersion(): BelongsTo
    {
        return $this->belongsTo(FlowVersion::class);
    }

    public function outgoingEdges(): HasMany
    {
        return $this->hasMany(FlowEdge::class, 'source_node_id');
    }
}
