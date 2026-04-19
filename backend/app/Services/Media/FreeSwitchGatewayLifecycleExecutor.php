<?php

namespace App\Services\Media;

class FreeSwitchGatewayLifecycleExecutor
{
    public function __construct(
        protected FreeSwitchCommandService $freeSwitch,
    ) {}

    public function execute(GatewayLifecyclePlan $plan, string $gatewayIdentifier): array
    {
        $commands = [];
        $stopped = false;
        $started = false;
        $stoppedProfile = null;

        if ($plan->action === GatewayLifecycleAction::STOP) {
            if ($plan->shouldKill && $plan->oldProfile !== null) {
                $commands[] = $this->sofia($plan->oldProfile, 'killgw', $gatewayIdentifier);
                $stopped = true;
                $stoppedProfile = $plan->oldProfile;
            } elseif ($plan->shouldKill && $plan->profile !== null) {
                $commands[] = $this->sofia($plan->profile, 'killgw', $gatewayIdentifier);
                $stopped = true;
                $stoppedProfile = $plan->profile;
            }
        }

        if ($plan->shouldReloadXml) {
            $commands[] = $this->freeSwitch->execute('reloadxml', [], false);
        }

        if ($plan->action !== GatewayLifecycleAction::STOP && $plan->shouldKill) {
            $killProfile = $plan->oldProfile ?? $plan->profile;

            if ($killProfile !== null) {
                $commands[] = $this->sofia($killProfile, 'killgw', $gatewayIdentifier);
                $stopped = true;
                $stoppedProfile = $killProfile;
            }
        }

        if ($plan->shouldRescan && $plan->profile !== null) {
            if (
                $plan->action === GatewayLifecycleAction::STOP
                && $stoppedProfile !== null
                && $stoppedProfile !== $plan->profile
            ) {
                $commands[] = $this->sofia($stoppedProfile, 'rescan');
            }

            $commands[] = $this->sofia($plan->profile, 'rescan');
        }

        if ($plan->shouldStart && $plan->profile !== null) {
            $commands[] = $this->sofia($plan->profile, 'startgw', $gatewayIdentifier);
            $started = true;
        }

        return [
            'action' => $plan->action,
            'reason' => $plan->reason,
            'profile' => $plan->profile,
            'old_profile' => $plan->oldProfile,
            'started' => $started,
            'stopped' => $stopped,
            'commands' => $commands,
        ];
    }

    protected function sofia(string $profile, string $operation, ?string $gatewayIdentifier = null): array
    {
        $arguments = ['profile', $profile, $operation];

        if ($operation !== 'rescan' && $gatewayIdentifier !== null) {
            $arguments[] = $gatewayIdentifier;
        }

        return $this->freeSwitch->execute('sofia', $arguments, false);
    }
}
