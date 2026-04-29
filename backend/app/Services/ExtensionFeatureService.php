<?php

namespace App\Services;

use App\Models\Extension;
use InvalidArgumentException;

class ExtensionFeatureService
{
    public function __construct(
        protected FollowMeEndpointBindingService $followMeEndpointBindingService,
    ) {}

    public function updateFeatures(Extension $extension, array $attributes): Extension
    {
        $payload = [];

        $followMeProvided = array_key_exists('follow_me_enabled', $attributes);
        $dndProvided = array_key_exists('dnd_enabled', $attributes);
        $destinationProvided = array_key_exists('follow_me_destination', $attributes);

        $followMeEnabled = $followMeProvided
            ? (bool) $attributes['follow_me_enabled']
            : (bool) $extension->follow_me_enabled;

        $dndEnabled = $dndProvided
            ? (bool) $attributes['dnd_enabled']
            : (bool) $extension->dnd_enabled;

        $destination = $destinationProvided
            ? ($attributes['follow_me_destination'] ?? null)
            : $extension->follow_me_destination;

        $effectiveDndEnabled = $dndEnabled;
        $effectiveFollowMeEnabled = $effectiveDndEnabled ? false : $followMeEnabled;

        if ($followMeProvided || $destinationProvided || $dndProvided) {
            if ($effectiveFollowMeEnabled && blank($destination)) {
                throw new InvalidArgumentException('follow_me_destination is required when follow_me_enabled is true.');
            }

            $payload['follow_me_enabled'] = $effectiveFollowMeEnabled;

            if ($destinationProvided) {
                $payload['follow_me_destination'] = blank($destination) ? null : $destination;
            }
        }

        if ($dndProvided) {
            $payload['dnd_enabled'] = $effectiveDndEnabled;
        }

        if (($payload['dnd_enabled'] ?? $extension->dnd_enabled) === true) {
            $payload['follow_me_enabled'] = false;
        }

        if ($payload !== []) {
            $extension->forceFill($payload)->save();
        }

        $freshExtension = $extension->fresh();
        $storedDestination = $payload['follow_me_destination'] ?? $freshExtension->follow_me_destination;
        $runtimeFollowMeEnabled = (($payload['dnd_enabled'] ?? $freshExtension->dnd_enabled) === true)
            ? false
            : ($payload['follow_me_enabled'] ?? $freshExtension->follow_me_enabled);

        $this->followMeEndpointBindingService->sync($freshExtension, [
            'follow_me_enabled' => $runtimeFollowMeEnabled,
            'follow_me_destination' => $runtimeFollowMeEnabled ? $storedDestination : null,
        ]);

        return $freshExtension;
    }
}
