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
                'description' => 'Detects self-calls and routes them to the account management menu (voicemail check), matching FusionPBX behavior.',
                'status' => 'active',
                'category' => 'Routing',
            ],
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
                'id' => 'tenant_isolation',
                'name' => 'Context-Isolated Routing',
                'description' => 'Strict multi-tenant traffic separation using domain-keyed dialplan contexts to prevent cross-tenant exposure.',
                'status' => 'active',
                'category' => 'Security',
            ],
        ];
    }

    protected function checkMultiRegStatus(): string
    {
        return SipProfileSetting::where('name', 'multiple-registrations')->where('value', 'contact')->exists()
            ? 'active' : 'inactive';
    }
}
