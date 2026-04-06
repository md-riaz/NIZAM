<?php

namespace App\Services\Call;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ReachabilityCache
{
    /**
     * @return array<string, mixed>|null
     */
    public function snapshotFor(string $tenantId, EndpointCandidate $candidate, int $maxAgeSeconds): ?array
    {
        foreach ($this->candidateKeys($tenantId, $candidate) as $key) {
            $snapshot = $this->get($key);

            if (! is_array($snapshot) || ! $this->isFresh($snapshot, $maxAgeSeconds)) {
                continue;
            }

            return $snapshot;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function rememberCandidate(string $tenantId, EndpointCandidate $candidate, array $snapshot, ?DateTimeInterface $observedAt = null): void
    {
        $payload = $this->normalizeSnapshot($candidate, $snapshot, $observedAt);

        foreach ($this->candidateKeys($tenantId, $candidate) as $key) {
            $this->put($key, $payload);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $registrations
     * @param  iterable<EndpointCandidate>  $candidates
     */
    public function rememberCandidateSnapshots(string $tenantId, iterable $candidates, array $registrations, ?DateTimeInterface $observedAt = null): void
    {
        foreach ($candidates as $candidate) {
            if (! $candidate instanceof EndpointCandidate || blank($candidate->sipAor)) {
                continue;
            }

            $registrationUser = $this->registrationUserFor($candidate);
            $snapshot = $registrationUser !== null
                ? ($registrations[$registrationUser] ?? null)
                : null;

            $this->rememberCandidate($tenantId, $candidate, [
                'registered' => (bool) data_get($snapshot, 'registered', false),
                'registration_user' => $registrationUser,
                'realm' => $this->realmFor($candidate),
                'contact' => data_get($snapshot, 'contact'),
                'user_agent' => data_get($snapshot, 'user_agent'),
                'network_ip' => data_get($snapshot, 'network_ip'),
                'network_port' => data_get($snapshot, 'network_port'),
                'source' => data_get($snapshot, 'source', 'esl_live'),
            ], $observedAt);
        }
    }

    public function markRegistered(string $tenantId, EndpointCandidate $candidate, array $attributes = [], ?DateTimeInterface $observedAt = null): void
    {
        $this->rememberCandidate($tenantId, $candidate, [
            'registered' => true,
            ...$attributes,
            'source' => $attributes['source'] ?? 'registration_event',
        ], $observedAt);
    }

    public function markUnregistered(string $tenantId, EndpointCandidate $candidate, array $attributes = [], ?DateTimeInterface $observedAt = null): void
    {
        $this->rememberCandidate($tenantId, $candidate, [
            'registered' => false,
            ...$attributes,
            'source' => $attributes['source'] ?? 'registration_event',
        ], $observedAt);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    protected function normalizeSnapshot(EndpointCandidate $candidate, array $snapshot, ?DateTimeInterface $observedAt = null): array
    {
        $moment = $observedAt
            ? CarbonImmutable::instance(new \DateTimeImmutable($observedAt->format(DATE_ATOM)))
            : CarbonImmutable::now();

        return [
            'endpoint_binding_id' => $candidate->endpointBindingId,
            'registration_user' => $snapshot['registration_user'] ?? $this->registrationUserFor($candidate),
            'realm' => $snapshot['realm'] ?? $this->realmFor($candidate),
            'registered' => (bool) ($snapshot['registered'] ?? false),
            'contact' => $snapshot['contact'] ?? null,
            'user_agent' => $snapshot['user_agent'] ?? null,
            'network_ip' => $snapshot['network_ip'] ?? null,
            'network_port' => $snapshot['network_port'] ?? null,
            'source' => $snapshot['source'] ?? 'cache',
            'observed_at' => $moment->toIso8601String(),
        ];
    }

    protected function isFresh(array $snapshot, int $maxAgeSeconds): bool
    {
        $observedAt = data_get($snapshot, 'observed_at');

        if (! is_string($observedAt) || $observedAt === '') {
            return false;
        }

        try {
            return CarbonImmutable::parse($observedAt)
                ->greaterThanOrEqualTo(CarbonImmutable::now()->subSeconds($maxAgeSeconds));
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    protected function candidateKeys(string $tenantId, EndpointCandidate $candidate): array
    {
        $keys = [sprintf('call-delivery:reachability:tenant:%s:endpoint:%s', $tenantId, $candidate->endpointBindingId)];

        $registrationUser = $this->registrationUserFor($candidate);

        if ($registrationUser !== null) {
            $keys[] = sprintf('call-delivery:reachability:tenant:%s:user:%s', $tenantId, $registrationUser);
        }

        return $keys;
    }

    protected function registrationUserFor(EndpointCandidate $candidate): ?string
    {
        if (blank($candidate->sipAor) || ! preg_match('/^sip:([^@]+)@/i', $candidate->sipAor, $matches)) {
            return null;
        }

        return strtolower($matches[1]);
    }

    protected function realmFor(EndpointCandidate $candidate): ?string
    {
        if (blank($candidate->sipAor) || ! preg_match('/@([^;>]+)$/i', $candidate->sipAor, $matches)) {
            return null;
        }

        return strtolower($matches[1]);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function get(string $key): ?array
    {
        try {
            $value = $this->store()->get($key);
        } catch (Throwable) {
            return null;
        }

        return is_array($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    protected function put(string $key, array $value): void
    {
        try {
            $this->store()->put($key, $value, $this->ttlSeconds());
        } catch (Throwable) {
            // Cache failures only trigger degraded live lookup; they should not break planning.
        }
    }

    protected function store(): Repository
    {
        $store = config('call_delivery.reachability.cache_store', 'redis');

        try {
            return Cache::store($store);
        } catch (Throwable) {
            return Cache::store();
        }
    }

    protected function ttlSeconds(): int
    {
        return max(1, (int) config('call_delivery.reachability.cache_ttl_seconds', 30));
    }
}
