<?php

namespace App\Modules;

use App\Modules\Contracts\NizamModule;

abstract class BaseModule implements NizamModule
{
    public function dependencies(): array
    {
        return [];
    }

    public function config(): array
    {
        return [];
    }

    public function register(): void {}

    public function boot(): void {}

    public function dialplanContributions(string $tenantDomain, string $destination): array
    {
        return [];
    }

    public function subscribedEvents(): array
    {
        return [];
    }

    public function handleEvent(string $eventType, array $data): void {}

    public function permissions(): array
    {
        return [];
    }

    public function migrationsPath(): ?string
    {
        return null;
    }

    public function routesFile(): ?string
    {
        return null;
    }

    public function policyHooks(): array
    {
        return [];
    }

    /**
     * Return module manifest for inspection.
     *
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        return [
            'name' => $this->name(),
            'version' => $this->version(),
            'description' => $this->description(),
            'dependencies' => $this->dependencies(),
        ];
    }
}
