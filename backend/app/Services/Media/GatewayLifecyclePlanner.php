<?php

namespace App\Services\Media;

use App\Models\Gateway;

class GatewayLifecyclePlanner
{
    /**
     * @var list<string>
     */
    private const REGISTRATION_FIELDS = [
        'host',
        'port',
        'username',
        'password',
        'realm',
        'proxy',
        'register_proxy',
        'transport',
        'profile',
        'register',
        'is_active',
    ];

    public function forCreate(Gateway $gateway): GatewayLifecyclePlan
    {
        if (! $gateway->is_active) {
            return new GatewayLifecyclePlan(
                action: GatewayLifecycleAction::NOOP,
                reason: 'created_inactive_gateway',
                profile: $gateway->profile,
                outcome: 'gateway_not_started_inactive',
            );
        }

        if ($this->shouldRegister($gateway)) {
            $hasCredentials = $this->hasCredentials($gateway);

            return new GatewayLifecyclePlan(
                action: $hasCredentials
                    ? GatewayLifecycleAction::START
                    : GatewayLifecycleAction::RESCAN_ONLY,
                reason: 'created_registering_gateway',
                profile: $gateway->profile,
                outcome: $hasCredentials
                    ? 'registration_started'
                    : 'registration_not_started_missing_credentials',
                shouldWriteFile: true,
                shouldStart: $hasCredentials,
                shouldReloadXml: true,
                shouldRescan: true,
            );
        }

        return new GatewayLifecyclePlan(
            action: GatewayLifecycleAction::RESCAN_ONLY,
            reason: 'created_non_registering_gateway',
            profile: $gateway->profile,
            outcome: 'gateway_rescanned_non_registering',
            shouldWriteFile: true,
            shouldReloadXml: true,
            shouldRescan: true,
        );
    }

    public function forUpdate(Gateway $gateway, array $original): GatewayLifecyclePlan
    {
        $profile = $gateway->profile;
        $oldProfile = $original['profile'] ?? $profile;

        if (($original['is_active'] ?? $gateway->is_active) && ! $gateway->is_active) {
            return new GatewayLifecyclePlan(
                action: GatewayLifecycleAction::STOP,
                reason: 'gateway_deactivated',
                profile: $profile,
                outcome: 'gateway_stopped_inactive',
                oldProfile: $oldProfile,
                shouldDeleteFile: true,
                shouldKill: true,
                shouldReloadXml: true,
                shouldRescan: true,
            );
        }

        if (($original['register'] ?? $gateway->register) && ! $gateway->register) {
            return new GatewayLifecyclePlan(
                action: GatewayLifecycleAction::STOP,
                reason: 'registration_disabled',
                profile: $profile,
                outcome: 'registration_stopped_disabled',
                oldProfile: $oldProfile,
                shouldWriteFile: $gateway->is_active,
                shouldDeleteFile: ! $gateway->is_active,
                shouldKill: true,
                shouldReloadXml: true,
                shouldRescan: true,
            );
        }

        if ($this->registrationFieldsChanged($gateway, $original)) {
            $shouldStart = $gateway->is_active && $gateway->register && $this->hasCredentials($gateway);

            return new GatewayLifecyclePlan(
                action: GatewayLifecycleAction::RESTART,
                reason: 'registration_fields_changed',
                profile: $profile,
                outcome: $shouldStart
                    ? 'registration_restarted'
                    : 'registration_not_started_missing_credentials',
                oldProfile: $oldProfile,
                shouldWriteFile: $gateway->is_active,
                shouldKill: true,
                shouldStart: $shouldStart,
                shouldReloadXml: true,
                shouldRescan: true,
            );
        }

        return new GatewayLifecyclePlan(
            action: GatewayLifecycleAction::RESCAN_ONLY,
            reason: 'non_registration_fields_changed',
            profile: $profile,
            outcome: 'gateway_rescanned_non_registration_change',
            oldProfile: $oldProfile,
            shouldWriteFile: $gateway->is_active,
            shouldReloadXml: true,
            shouldRescan: true,
        );
    }

    public function forDelete(Gateway $gateway): GatewayLifecyclePlan
    {
        return new GatewayLifecyclePlan(
            action: GatewayLifecycleAction::STOP,
            reason: 'gateway_deleted',
            profile: $gateway->profile,
            outcome: 'gateway_stopped_deleted',
            shouldDeleteFile: true,
            shouldKill: true,
            shouldReloadXml: true,
            shouldRescan: true,
        );
    }

    protected function shouldRegister(Gateway $gateway): bool
    {
        return (bool) $gateway->register;
    }

    protected function hasCredentials(Gateway $gateway): bool
    {
        return filled($gateway->username)
            && filled($gateway->password)
            && filled($gateway->host)
            && filled($gateway->profile);
    }

    protected function registrationFieldsChanged(Gateway $gateway, array $original): bool
    {
        foreach (self::REGISTRATION_FIELDS as $field) {
            $currentValue = $gateway->{$field};
            $originalValue = $original[$field] ?? $currentValue;

            if ($currentValue !== $originalValue) {
                return true;
            }
        }

        return false;
    }
}
