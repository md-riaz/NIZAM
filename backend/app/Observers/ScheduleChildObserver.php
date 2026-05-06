<?php

namespace App\Observers;

use App\Models\EndpointBinding;
use App\Models\FlowCompiledArtifact;
use App\Models\FlowEdge;
use App\Models\FlowNode;
use App\Models\FlowVersion;
use App\Models\Team;
use App\Models\TeamMember;
use App\Services\Flow\FlowArtifactService;
use App\Services\OrganizationManifestBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ScheduleChildObserver
{
    public function __construct(
        protected OrganizationManifestBuilder $manifestBuilder
    ) {}

    protected function rebuildManifest(Model $model): void
    {
        try {
            $organization = match (true) {
                $model instanceof Team => $model->organization,
                $model instanceof TeamMember => $model->team?->organization,
                $model instanceof EndpointBinding => $model->organization,
                $model instanceof FlowNode => $model->flowVersion?->flow?->organization,
                $model instanceof FlowEdge => $model->flowVersion?->flow?->organization,
                $model instanceof FlowVersion => $model->flow?->organization,
                $model instanceof FlowCompiledArtifact => $model->organization ?? $model->flowVersion?->flow?->organization,
                default => $model->schedule?->organization,
            };

            if (! $organization) {
                return;
            }

            if ($model instanceof Team) {
                app(FlowArtifactService::class)->refreshTeamRoutingArtifactsForTeam($model);
                return;
            }

            if ($model instanceof TeamMember && $model->team) {
                app(FlowArtifactService::class)->refreshTeamRoutingArtifactsForTeam($model->team);
                return;
            }

            if ($model instanceof EndpointBinding
                && data_get($model->metadata, 'managed_by') === \App\Services\FollowMeEndpointBindingService::class) {
                return;
            }
            $this->manifestBuilder->buildAndActivate($organization);
        } catch (\Exception $e) {
            Log::error('Failed to rebuild manifest on schedule child change', [
                'model' => get_class($model),
                'id' => $model->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function created(Model $model): void { $this->rebuildManifest($model); }
    public function updated(Model $model): void { $this->rebuildManifest($model); }
    public function deleted(Model $model): void { $this->rebuildManifest($model); }
}
