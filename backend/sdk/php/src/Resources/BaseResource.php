<?php

namespace Nizam\Sdk\Resources;

use Nizam\Sdk\NizamClient;

abstract class BaseResource
{
    protected NizamClient $client;

    protected ?string $organizationId;

    public function __construct(NizamClient $client, ?string $organizationId = null)
    {
        $this->client = $client;
        $this->organizationId = $organizationId;
    }

    protected function organizationPath(string $path = ''): string
    {
        return "organizations/{$this->organizationId}".(! empty($path) ? "/{$path}" : '');
    }
}
