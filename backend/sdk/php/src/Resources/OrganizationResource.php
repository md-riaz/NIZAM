<?php

namespace Nizam\Sdk\Resources;

use Nizam\Sdk\NizamClient;

class OrganizationResource extends BaseResource
{
    public function __construct(NizamClient $client)
    {
        parent::__construct($client);
    }

    public function list(array $query = []): array
    {
        return $this->client->get('organizations', $query);
    }

    public function create(array $data): array
    {
        return $this->client->post('organizations', $data);
    }

    public function get(string $id): array
    {
        return $this->client->get("organizations/{$id}");
    }

    public function update(string $id, array $data): array
    {
        return $this->client->put("organizations/{$id}", $data);
    }

    public function delete(string $id): array
    {
        return $this->client->delete("organizations/{$id}");
    }

    public function settings(string $id): array
    {
        return $this->client->get("organizations/{$id}/settings");
    }

    public function updateSettings(string $id, array $settings): array
    {
        return $this->client->put("organizations/{$id}/settings", $settings);
    }

}
