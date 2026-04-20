<?php

namespace Nizam\Sdk\Resources;

class WebhookResource extends BaseResource
{
    public function list(array $query = []): array
    {
        return $this->client->get($this->organizationPath('webhooks'), $query);
    }

    public function create(array $data): array
    {
        return $this->client->post($this->organizationPath('webhooks'), $data);
    }

    public function get(string $id): array
    {
        return $this->client->get($this->organizationPath("webhooks/{$id}"));
    }

    public function update(string $id, array $data): array
    {
        return $this->client->put($this->organizationPath("webhooks/{$id}"), $data);
    }

    public function delete(string $id): array
    {
        return $this->client->delete($this->organizationPath("webhooks/{$id}"));
    }

    public function deliveryAttempts(string $id): array
    {
        return $this->client->get($this->organizationPath("webhooks/{$id}/delivery-attempts"));
    }

    public function deliveryStats(string $id): array
    {
        return $this->client->get($this->organizationPath("webhooks/{$id}/delivery-stats"));
    }
}
