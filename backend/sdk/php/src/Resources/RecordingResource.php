<?php

namespace Nizam\Sdk\Resources;

class RecordingResource extends BaseResource
{
    public function list(array $query = []): array
    {
        return $this->client->get($this->organizationPath('recordings'), $query);
    }

    public function get(string $id): array
    {
        return $this->client->get($this->organizationPath("recordings/{$id}"));
    }

    public function delete(string $id): array
    {
        return $this->client->delete($this->organizationPath("recordings/{$id}"));
    }
}
