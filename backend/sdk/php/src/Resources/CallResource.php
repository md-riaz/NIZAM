<?php

namespace Nizam\Sdk\Resources;

class CallResource extends BaseResource
{
    public function originate(string $extension, string $destination): array
    {
        return $this->client->post($this->tenantPath('calls/originate'), [
            'extension' => $extension,
            'destination' => $destination,
        ]);
    }

    public function status(array $query = []): array
    {
        return $this->client->get($this->tenantPath('calls/status'), $query);
    }

    public function hangup(string $callUuid): array
    {
        return $this->client->post($this->tenantPath('calls/hangup'), [
            'call_uuid' => $callUuid,
        ]);
    }

    public function transfer(string $callUuid, string $destination): array
    {
        return $this->client->post($this->tenantPath('calls/transfer'), [
            'call_uuid' => $callUuid,
            'destination' => $destination,
        ]);
    }

    public function hold(string $callUuid): array
    {
        return $this->client->post($this->tenantPath('calls/hold'), [
            'call_uuid' => $callUuid,
        ]);
    }

    public function toggleRecording(string $callUuid): array
    {
        return $this->client->post($this->tenantPath('calls/recording'), [
            'call_uuid' => $callUuid,
        ]);
    }
}
