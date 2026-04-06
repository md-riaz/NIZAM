<?php

namespace Nizam\Sdk\Resources;

class WebhookResource extends BaseResource
{
    public function list(array $query = []): array
    {
        return $this->client->get($this->tenantPath('webhooks'), $query);
    }

    public function create(array $data): array
    {
        return $this->client->post($this->tenantPath('webhooks'), $data);
    }

    public function get(string $id): array
    {
        return $this->client->get($this->tenantPath("webhooks/{$id}"));
    }

    public function update(string $id, array $data): array
    {
        return $this->client->put($this->tenantPath("webhooks/{$id}"), $data);
    }

    public function delete(string $id): array
    {
        return $this->client->delete($this->tenantPath("webhooks/{$id}"));
    }

    public function deliveryAttempts(string $id): array
    {
        return $this->client->get($this->tenantPath("webhooks/{$id}/delivery-attempts"));
    }

    public function deliveryStats(string $id): array
    {
        return $this->client->get($this->tenantPath("webhooks/{$id}/delivery-stats"));
    }
}
