<?php

namespace Nizam\Sdk\Resources;

use Nizam\Sdk\NizamClient;

class TenantResource extends BaseResource
{
    public function __construct(NizamClient $client)
    {
        parent::__construct($client);
    }

    public function list(array $query = []): array
    {
        return $this->client->get('tenants', $query);
    }

    public function create(array $data): array
    {
        return $this->client->post('tenants', $data);
    }

    public function get(string $id): array
    {
        return $this->client->get("tenants/{$id}");
    }

    public function update(string $id, array $data): array
    {
        return $this->client->put("tenants/{$id}", $data);
    }

    public function delete(string $id): array
    {
        return $this->client->delete("tenants/{$id}");
    }

    public function settings(string $id): array
    {
        return $this->client->get("tenants/{$id}/settings");
    }

    public function updateSettings(string $id, array $settings): array
    {
        return $this->client->put("tenants/{$id}/settings", $settings);
    }

    public function provision(array $data): array
    {
        return $this->client->post('tenants/provision', $data);
    }
}
