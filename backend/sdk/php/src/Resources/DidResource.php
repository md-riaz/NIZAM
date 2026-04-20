<?php

namespace Nizam\Sdk\Resources;

class DidResource extends BaseResource
{
    public function list(array $query = []): array
    {
        return $this->client->get($this->organizationPath('dids'), $query);
    }

    public function create(array $data): array
    {
        return $this->client->post($this->organizationPath('dids'), $data);
    }

    public function get(string $id): array
    {
        return $this->client->get($this->organizationPath("dids/{$id}"));
    }

    public function update(string $id, array $data): array
    {
        return $this->client->put($this->organizationPath("dids/{$id}"), $data);
    }

    public function delete(string $id): array
    {
        return $this->client->delete($this->organizationPath("dids/{$id}"));
    }
}
