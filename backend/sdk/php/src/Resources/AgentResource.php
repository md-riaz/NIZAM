<?php

namespace Nizam\Sdk\Resources;

class AgentResource extends BaseResource
{
    public function list(array $query = []): array
    {
        return $this->client->get($this->organizationPath('agents'), $query);
    }

    public function create(array $data): array
    {
        return $this->client->post($this->organizationPath('agents'), $data);
    }

    public function get(string $id): array
    {
        return $this->client->get($this->organizationPath("agents/{$id}"));
    }

    public function update(string $id, array $data): array
    {
        return $this->client->put($this->organizationPath("agents/{$id}"), $data);
    }

    public function delete(string $id): array
    {
        return $this->client->delete($this->organizationPath("agents/{$id}"));
    }

    public function changeState(string $id, string $state, ?string $pauseReason = null): array
    {
        $data = ['state' => $state];
        if ($pauseReason !== null) {
            $data['pause_reason'] = $pauseReason;
        }

        return $this->client->post($this->organizationPath("agents/{$id}/state"), $data);
    }
}
