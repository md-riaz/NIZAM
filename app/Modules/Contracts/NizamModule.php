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
     * Module dependencies (other module names required).
     *
     * @return array<string>
     */
    public function dependencies(): array;

    /**
     * Module configuration defaults.
     *
     * @return array<string, mixed>
     */
    public function config(): array;

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

    /**
     * Return the path to the module's migrations directory, or null if none.
     */
    public function migrationsPath(): ?string;

    /**
     * Return the path to the module's route file, or null if none.
     */
    public function routesFile(): ?string;

    /**
     * Return policy hooks this module provides.
     *
     * @return array<string, callable>
     */
    public function policyHooks(): array;
}
