<?php

namespace App\Services\Admin;

use App\Models\SipProfileSetting;

class CapabilityService
{
    public function getCapabilities(): array
    {
        return [
            [
                'id' => 'self_call_management',
                'name' => 'Self-Call Management',
                'description' => 'Detects self-calls and routes them to the account management menu (voicemail check) using the platform\'s native call-management flow.',
                'status' => 'active',
                'category' => 'Routing',
            ],
            $this->customLuaScriptCapability(
                id: 'lua_directed_pickup',
                name: 'Lua Runtime: Directed Pickup',
                script: 'custom/_directed_pickup.lua',
                description: 'Custom helper for directed call pickup with native platform integration.'
            ),
            $this->customLuaScriptCapability(
                id: 'lua_group_pickup',
                name: 'Lua Runtime: Group Pickup',
                script: 'custom/_group_pickup.lua',
                description: 'Custom helper for group pickup using organization-scoped ring-group membership.'
            ),
            $this->customLuaScriptCapability(
                id: 'lua_team_ring',
                name: 'Lua Runtime: Team Ring',
                script: 'custom/_team_ring.lua',
                description: 'Custom helper for team ringing, timeout handling, and transfer fallback behavior.'
            ),
            $this->customLuaScriptCapability(
                id: 'lua_valet_park',
                name: 'Lua Runtime: Valet Park',
                script: 'custom/_valet_park.lua',
                description: 'Custom helper that selects an open valet orbit and parks calls automatically.'
            ),
            [
                'id' => 'multi_registration',
                'name' => 'Multi-Registration Support',
                'description' => 'Allows up to 5 simultaneous devices per extension using contact-based registration tracking.',
                'status' => $this->checkMultiRegStatus(),
                'category' => 'Security',
            ],
            [
                'id' => 'optimized_directory',
                'name' => 'Optimized Directory Service',
                'description' => 'Filtered XML-CURL lookups for zero-lag softphone connection by fetching only the requested user.',
                'status' => 'active',
                'category' => 'Performance',
            ],
            [
                'id' => 'organization_isolation',
                'name' => 'Context-Isolated Routing',
                'description' => 'Strict multi-organization traffic separation using domain-keyed dialplan contexts to prevent cross-organization exposure.',
                'status' => 'active',
                'category' => 'Security',
            ],
        ];
    }

    protected function checkMultiRegStatus(): string
    {
        return SipProfileSetting::query()
            ->where('name', 'multiple-registrations')
            ->where('value', 'contact')
            ->where('is_enabled', true)
            ->whereHas('profile', function ($query) {
                $query->where('name', 'internal');
            })
            ->exists()
                ? 'active'
                : 'inactive';
    }

    protected function customLuaScriptCapability(string $id, string $name, string $script, string $description): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'status' => file_exists(base_path('docker/freeswitch/scripts/' . $script)) ? 'active' : 'inactive',
            'category' => 'Runtime Script',
        ];
    }
}
