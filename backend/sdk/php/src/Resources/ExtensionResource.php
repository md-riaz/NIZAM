<?php

namespace Nizam\Sdk\Resources;

class ExtensionResource extends BaseResource
{
    public function list(array $query = []): array
    {
        return $this->client->get($this->organizationPath('extensions'), $query);
    }

    public function create(array $data): array
    {
        return $this->client->post($this->organizationPath('extensions'), $data);
    }

    public function get(string $id): array
    {
        return $this->client->get($this->organizationPath("extensions/{$id}"));
    }

    public function update(string $id, array $data): array
    {
        return $this->client->put($this->organizationPath("extensions/{$id}"), $data);
    }

    public function delete(string $id): array
    {
        return $this->client->delete($this->organizationPath("extensions/{$id}"));
    }
}
