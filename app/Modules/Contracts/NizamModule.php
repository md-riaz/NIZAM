<?php

namespace App\Modules\Contracts;

interface NizamModule
{
    /**
     * Unique identifier for the module.
     */
    public function name(): string;

    /**
     * Human-readable description.
     */
    public function description(): string;

    /**
     * Module version string.
     */
    public function version(): string;

    /**
     * Register the module: bindings, event listeners, etc.
     */
    public function register(): void;

    /**
     * Boot the module after all modules are registered.
     */
    public function boot(): void;

    /**
     * Return dialplan contributions (XML fragments keyed by priority).
     *
     * @return array<int, string>
     */
    public function dialplanContributions(string $tenantDomain, string $destination): array;

    /**
     * Return event types this module subscribes to.
     *
     * @return array<string>
     */
    public function subscribedEvents(): array;

    /**
     * Handle an event dispatched by the system.
     */
    public function handleEvent(string $eventType, array $data): void;

    /**
     * Return additional permissions this module introduces.
     *
     * @return array<string>
     */
    public function permissions(): array;
}
