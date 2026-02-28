<?php

namespace Nizam\Sdk\Resources;

class ExtensionResource extends BaseResource
{
    public function list(array $query = []): array
    {
        return $this->client->get($this->tenantPath('extensions'), $query);
    }

    public function create(array $data): array
    {
        return $this->client->post($this->tenantPath('extensions'), $data);
    }

    public function get(string $id): array
    {
        return $this->client->get($this->tenantPath("extensions/{$id}"));
    }

    public function update(string $id, array $data): array
    {
        return $this->client->put($this->tenantPath("extensions/{$id}"), $data);
    }

    public function delete(string $id): array
    {
        return $this->client->delete($this->tenantPath("extensions/{$id}"));
    }
}
