<?php

namespace Nizam\Sdk\Resources;

class QueueResource extends BaseResource
{
    public function list(array $query = []): array
    {
        return $this->client->get($this->tenantPath('queues'), $query);
    }

    public function create(array $data): array
    {
        return $this->client->post($this->tenantPath('queues'), $data);
    }

    public function get(string $id): array
    {
        return $this->client->get($this->tenantPath("queues/{$id}"));
    }

    public function update(string $id, array $data): array
    {
        return $this->client->put($this->tenantPath("queues/{$id}"), $data);
    }

    public function delete(string $id): array
    {
        return $this->client->delete($this->tenantPath("queues/{$id}"));
    }

    public function addMember(string $queueId, array $data): array
    {
        return $this->client->post($this->tenantPath("queues/{$queueId}/members"), $data);
    }

    public function removeMember(string $queueId, string $agentId): array
    {
        return $this->client->delete($this->tenantPath("queues/{$queueId}/members/{$agentId}"));
    }

    public function members(string $queueId): array
    {
        return $this->client->get($this->tenantPath("queues/{$queueId}/members"));
    }

    public function realtimeMetrics(string $queueId): array
    {
        return $this->client->get($this->tenantPath("queues/{$queueId}/metrics/realtime"));
    }

    public function metricsHistory(string $queueId, array $query = []): array
    {
        return $this->client->get($this->tenantPath("queues/{$queueId}/metrics/history"), $query);
    }
}
