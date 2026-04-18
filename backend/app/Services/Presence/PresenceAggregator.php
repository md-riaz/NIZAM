<?php

namespace App\Services\Presence;

use App\Models\CallDeliveryAttempt;
use App\Models\DeviceRegistrationSnapshot;
use App\Models\Extension;
use App\Models\User;
use Illuminate\Support\Collection;

class PresenceAggregator
{
    /**
     * Build a merged presence view for a business user.
     *
     * @param  iterable<CallDeliveryAttempt>|null  $activeCallAttempts
     * @return array<string, mixed>
     */
    public function forUser(User $user, iterable $activeCallAttempts = []): array
    {
        $extensions = $user->relationLoaded('extensions')
            ? $user->extensions
            : $user->extensions()->with(['deviceProfiles', 'deviceRegistrationSnapshots'])->get();

        $deviceProfiles = $user->relationLoaded('deviceProfiles')
            ? $user->deviceProfiles
            : $user->deviceProfiles()->with('extension')->get();

        $extensionDevices = $extensions
            ->flatMap(fn (Extension $extension) => $extension->relationLoaded('deviceProfiles')
                ? $extension->deviceProfiles
                : $extension->deviceProfiles()->get());

        $devices = $deviceProfiles
            ->merge($extensionDevices)
            ->unique('id')
            ->values();

        $registrationSnapshots = $extensions
            ->flatMap(fn (Extension $extension) => $extension->relationLoaded('deviceRegistrationSnapshots')
                ? $extension->deviceRegistrationSnapshots
                : $extension->deviceRegistrationSnapshots()->get())
            ->sortByDesc(fn (DeviceRegistrationSnapshot $snapshot) => optional($snapshot->observed_at)?->getTimestamp() ?? 0)
            ->values();

        $activeCalls = collect($activeCallAttempts)
            ->filter(fn ($attempt) => $attempt instanceof CallDeliveryAttempt)
            ->values();

        $primaryExtension = $extensions->firstWhere('is_primary', true) ?? $extensions->first();
        $status = $this->resolveStatus($user->role ?? 'user', $registrationSnapshots, $activeCalls);

        return [
            'user_id' => $user->id,
            'organization_id' => $user->organization_id,
            'status' => $status,
            'availability' => $this->resolveAvailability($status),
            'primary_extension_id' => $primaryExtension?->id,
            'extension_ids' => $extensions->pluck('id')->values()->all(),
            'registered_device_count' => $registrationSnapshots
                ->unique('registration_key')
                ->where('registered', true)
                ->count(),
            'active_call_count' => $activeCalls->count(),
            'supports_softphone' => $devices->contains(fn ($device) => $device->is_active),
            'devices' => $devices->map(function ($device): array {
                return [
                    'id' => $device->id,
                    'name' => $device->name,
                    'extension_id' => $device->extension_id,
                    'is_active' => (bool) $device->is_active,
                    'is_assigned_directly' => $device->user_id !== null,
                ];
            })->values()->all(),
            'registrations' => $registrationSnapshots->map(function (DeviceRegistrationSnapshot $snapshot): array {
                return [
                    'extension_id' => $snapshot->extension_id,
                    'registration_key' => $snapshot->registration_key,
                    'registered' => (bool) $snapshot->registered,
                    'user_agent' => $snapshot->user_agent,
                    'network_ip' => $snapshot->network_ip,
                    'observed_at' => $snapshot->observed_at?->toIso8601String(),
                ];
            })->all(),
            'active_calls' => $activeCalls->map(function (CallDeliveryAttempt $attempt): array {
                return [
                    'id' => $attempt->id,
                    'status' => $attempt->status,
                    'attempt_type' => $attempt->attempt_type,
                    'endpoint_binding_id' => $attempt->endpoint_binding_id,
                    'call_session_id' => $attempt->call_session_id,
                    'started_at' => $attempt->started_at?->toIso8601String(),
                ];
            })->all(),
        ];
    }

    /**
     * @param  Collection<int, DeviceRegistrationSnapshot>  $registrationSnapshots
     * @param  Collection<int, CallDeliveryAttempt>  $activeCalls
     */
    private function resolveStatus(string $role, Collection $registrationSnapshots, Collection $activeCalls): string
    {
        if ($activeCalls->contains(fn (CallDeliveryAttempt $attempt) => $attempt->status === CallDeliveryAttempt::STATUS_RINGING)) {
            return 'ringing';
        }

        if ($activeCalls->isNotEmpty()) {
            return 'on_call';
        }

        if ($registrationSnapshots->contains(fn (DeviceRegistrationSnapshot $snapshot) => $snapshot->registered)) {
            return 'available';
        }

        return in_array($role, ['superadmin', 'admin'], true) ? 'available' : 'offline';
    }

    private function resolveAvailability(string $status): string
    {
        return match ($status) {
            'ringing', 'on_call' => 'engaged',
            'available' => 'available',
            default => 'offline',
        };
    }
}
