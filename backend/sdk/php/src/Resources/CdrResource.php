<?php

namespace Nizam\Sdk\Resources;

class CdrResource extends BaseResource
{
    public function list(array $query = []): array
    {
        return $this->client->get($this->tenantPath('cdrs'), $query);
    }

    public function get(string $id): array
    {
        return $this->client->get($this->tenantPath("cdrs/{$id}"));
    }

    public function export(array $query = []): array
    {
        return $this->client->get($this->tenantPath('cdrs/export'), $query);
    }
}
