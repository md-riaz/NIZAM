<?php

namespace App\Domain\Flow;

use App\Models\CallSession;

class CallContext
{
    public function __construct(
        public readonly CallSession $callSession,
        public readonly array $variables = [],
    ) {}

    public function callUuid(): string
    {
        return $this->callSession->call_uuid;
    }

    public function tenantId(): string
    {
        return $this->callSession->tenant_id;
    }

    public function currentNodeId(): ?string
    {
        return $this->callSession->current_node_id;
    }

    public function variable(string $key, mixed $default = null): mixed
    {
        return $this->variables[$key] ?? $default;
    }

    public function withVariables(array $variables): self
    {
        return new self($this->callSession, array_merge($this->variables, $variables));
    }
}
