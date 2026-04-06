<?php

namespace Nizam\Sdk\Resources;

class AgentResource extends BaseResource
{
    public function list(array $query = []): array
    {
        return $this->client->get($this->tenantPath('agents'), $query);
    }

    public function create(array $data): array
    {
        return $this->client->post($this->tenantPath('agents'), $data);
    }

    public function get(string $id): array
    {
        return $this->client->get($this->tenantPath("agents/{$id}"));
    }

    public function update(string $id, array $data): array
    {
        return $this->client->put($this->tenantPath("agents/{$id}"), $data);
    }

    public function delete(string $id): array
    {
        return $this->client->delete($this->tenantPath("agents/{$id}"));
    }

    public function changeState(string $id, string $state, ?string $pauseReason = null): array
    {
        $data = ['state' => $state];
        if ($pauseReason !== null) {
            $data['pause_reason'] = $pauseReason;
        }

        return $this->client->post($this->tenantPath("agents/{$id}/state"), $data);
    }
}
