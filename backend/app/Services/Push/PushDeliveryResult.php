<?php

namespace App\Services\Push;

class PushDeliveryResult
{
    private function __construct(
        public readonly bool $success,
        public readonly ?string $channel,
        public readonly ?string $providerMessageId,
        public readonly ?string $error,
        /** @var array<string, mixed> */
        public readonly array $meta = [],
    ) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function sent(string $channel, ?string $providerMessageId = null, array $meta = []): self
    {
        return new self(
            success: true,
            channel: $channel,
            providerMessageId: $providerMessageId,
            error: null,
            meta: $meta,
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function failed(string $error, array $meta = []): self
    {
        return new self(
            success: false,
            channel: null,
            providerMessageId: null,
            error: $error,
            meta: $meta,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'success' => $this->success,
            'channel' => $this->channel,
            'provider_message_id' => $this->providerMessageId,
            'error' => $this->error,
            ...$this->meta,
        ], fn ($v) => $v !== null);
    }
}
