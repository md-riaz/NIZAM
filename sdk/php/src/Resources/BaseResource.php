<?php

namespace Nizam\Sdk\Resources;

use Nizam\Sdk\NizamClient;

abstract class BaseResource
{
    protected NizamClient $client;

    protected ?string $tenantId;

    public function __construct(NizamClient $client, ?string $tenantId = null)
    {
        $this->client = $client;
        $this->tenantId = $tenantId;
    }

    protected function tenantPath(string $path = ''): string
    {
        return "tenants/{$this->tenantId}".(! empty($path) ? "/{$path}" : '');
    }
}
