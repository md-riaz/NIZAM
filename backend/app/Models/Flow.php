<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Flow extends Model
{
    use HasUuids, \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'active_version_id',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(FlowVersion::class);
    }

    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(FlowVersion::class, 'active_version_id');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(FlowVersion::class)->latest('version_number');
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
