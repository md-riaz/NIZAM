<?php

namespace Nizam\Sdk\Resources;

class CallEventResource extends BaseResource
{
    public function list(array $query = []): array
    {
        return $this->client->get($this->organizationPath('call-events'), $query);
    }

    public function trace(string $callUuid): array
    {
        return $this->client->get($this->organizationPath("call-events/{$callUuid}/trace"));
    }

    public function replay(string $eventId): array
    {
        return $this->client->get($this->organizationPath("call-events/replay/{$eventId}"));
    }

    public function redispatch(string $eventId): array
    {
        return $this->client->post($this->organizationPath("call-events/redispatch/{$eventId}"));
    }
}
