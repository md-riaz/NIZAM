<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Flow extends Model
{
    use HasUuids, \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'active_version_id',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(FlowVersion::class);
    }

    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(FlowVersion::class, 'active_version_id');
    }

    public function activeRoutingGraphArtifact(): HasOneThrough
    {
        return $this->hasOneThrough(
            FlowCompiledArtifact::class,
            FlowVersion::class,
            'flow_id',
            'flow_version_id',
            'active_version_id',
            'id',
        )->where('flow_compiled_artifacts.artifact_type', FlowCompiledArtifact::ARTIFACT_TYPE_ROUTING_GRAPH);
    }
}
