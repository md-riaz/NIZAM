<?php

namespace App\Services;

use App\Models\Extension;
use App\Models\Tenant;
use Illuminate\Support\Str;

class TenantProvisioningService
{
    /**
     * Provision a new tenant with zero-touch setup.
     *
     * Creates tenant, assigns domain/subdomain, bootstraps default extension 1000.
     */
    public function provision(array $params): array
    {
        $name = $params['name'];
        $slug = $params['slug'] ?? Str::slug($name).'-'.Str::random(4);
        $domain = $params['domain'] ?? $slug.'.nizam.local';

        $tenant = Tenant::create([
            'name' => $name,
            'domain' => $domain,
            'slug' => $slug,
            'status' => $params['status'] ?? Tenant::STATUS_TRIAL,
            'max_extensions' => $params['max_extensions'] ?? 10,
            'max_concurrent_calls' => $params['max_concurrent_calls'] ?? 5,
            'max_dids' => $params['max_dids'] ?? 5,
            'max_ring_groups' => $params['max_ring_groups'] ?? 3,
            'is_active' => true,
            'settings' => $params['settings'] ?? [],
        ]);

        // Bootstrap default extension 1000
        $extension = $tenant->extensions()->create([
            'extension' => '1000',
            'password' => Str::random(16),
            'directory_first_name' => 'Default',
            'directory_last_name' => 'Extension',
            'is_active' => true,
            'voicemail_enabled' => true,
            'voicemail_pin' => (string) random_int(1000, 9999),
        ]);

        return [
            'tenant' => $tenant,
            'default_extension' => $extension,
            'provisioning_domain' => $domain,
        ];
    }
}
