<?php

namespace Nizam\Sdk\Resources;

class DidResource extends BaseResource
{
    public function list(array $query = []): array
    {
        return $this->client->get($this->tenantPath('dids'), $query);
    }

    public function create(array $data): array
    {
        return $this->client->post($this->tenantPath('dids'), $data);
    }

    public function get(string $id): array
    {
        return $this->client->get($this->tenantPath("dids/{$id}"));
    }

    public function update(string $id, array $data): array
    {
        return $this->client->put($this->tenantPath("dids/{$id}"), $data);
    }

    public function delete(string $id): array
    {
        return $this->client->delete($this->tenantPath("dids/{$id}"));
    }
}
