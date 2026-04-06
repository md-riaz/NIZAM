<?php

namespace Nizam\Sdk\Resources;

class RecordingResource extends BaseResource
{
    public function list(array $query = []): array
    {
        return $this->client->get($this->tenantPath('recordings'), $query);
    }

    public function get(string $id): array
    {
        return $this->client->get($this->tenantPath("recordings/{$id}"));
    }

    public function delete(string $id): array
    {
        return $this->client->delete($this->tenantPath("recordings/{$id}"));
    }
}
