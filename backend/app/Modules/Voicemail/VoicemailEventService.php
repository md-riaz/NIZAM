<?php

namespace App\Modules\Voicemail;

use App\Models\CallEventLog;
use App\Models\Organization;
use Illuminate\Support\Facades\Log;

class VoicemailEventService
{
    /**
     * Convert a FreeSWITCH voicemail maintenance event into a canonical payload.
     *
     * @return array<string, mixed>|null
     */
    public function handleMaintenanceEvent(array $event): ?array
    {
        $action = $event['VM-Action'] ?? '';
        if ($action !== 'leave-message') {
            return null;
        }

        $organization = $this->resolveOrganization($event);
        if (! $organization) {
            return null;
        }

        $mailbox = (string) ($event['VM-User'] ?? 'unknown');
        $resolvedStoragePath = $this->resolveStoragePath($event, $organization->domain, $mailbox);
        $rawStoragePath = $this->resolveRawStoragePath($event);

        $metadata = [
            'user' => (string) ($event['VM-User'] ?? ''),
            'domain' => (string) ($event['VM-Domain'] ?? $organization->domain),
            'caller_id_number' => (string) ($event['VM-Caller-ID-Number'] ?? ''),
            'caller_id_name' => (string) ($event['VM-Caller-ID-Name'] ?? ''),
            'message_len' => (string) ($event['VM-Message-Len'] ?? '0'),
            'storage_disk' => 'local',
            'storage_driver' => 'local-first',
            'storage_path' => $resolvedStoragePath,
            'storage_reference' => $resolvedStoragePath,
            'raw_storage_path' => $rawStoragePath,
            'source_event' => 'vm::maintenance',
        ];

        foreach (['VM-Message-File', 'VM-File-Path', 'VM-Message-UUID', 'VM-Message-Id'] as $key) {
            if (! isset($event[$key]) || $event[$key] === '') {
                continue;
            }

            $metadataKey = strtolower(str_replace(['VM-', '-'], ['', '_'], $key));
            $metadata[$metadataKey] = in_array($key, ['VM-Message-File', 'VM-File-Path'], true)
                ? $resolvedStoragePath
                : (string) $event[$key];
        }

        if ($rawStoragePath !== null) {
            $metadata['raw_message_file'] = $rawStoragePath;
        }

        return $this->buildPayload($organization->id, $metadata);
    }

    /**
     * Handle a canonical voicemail.received payload dispatched through the module registry.
     */
    public function handleReceivedPayload(array $data): void
    {
        Log::debug('Voicemail module handled received event', [
            'organization_id' => $data['organization_id'] ?? null,
            'call_uuid' => $data['call_uuid'] ?? null,
            'storage_disk' => data_get($data, 'metadata.storage_disk'),
            'storage_path' => data_get($data, 'metadata.storage_path'),
        ]);
    }

    protected function resolveOrganization(array $event): ?Organization
    {
        $domain = $event['VM-Domain']
            ?? $event['variable_domain_name']
            ?? $event['variable_sip_req_host']
            ?? null;

        if (! $domain) {
            return null;
        }

        $organization = Organization::query()
            ->where('domain', $domain)
            ->where('is_active', true)
            ->first();

        if (! $organization || ! $organization->isOperational()) {
            return null;
        }

        return $organization;
    }

    protected function resolveStoragePath(array $event, string $domain, string $mailbox): string
    {
        $rawPath = $this->resolveRawStoragePath($event);

        if ($rawPath !== null) {
            return $this->canonicalizeStoragePath($rawPath);
        }

        return $this->canonicalizeStoragePath(sprintf(
            'voicemail/%s/%s',
            trim($domain, '/'),
            trim($mailbox, '/')
        ));
    }

    protected function resolveRawStoragePath(array $event): ?string
    {
        foreach (['VM-Message-File', 'VM-File-Path'] as $key) {
            $value = $event[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function canonicalizeStoragePath(string $path): string
    {
        $normalized = trim(str_replace('\\', '/', $path));

        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('#/+#', '/', $normalized) ?? $normalized;

        if ($normalized === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z]:\//', $normalized) === 1) {
            return ltrim(substr($normalized, 2), '/');
        }

        return ltrim($normalized, '/');
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    protected function buildPayload(string $organizationId, array $metadata): array
    {
        return [
            'organization_id' => $organizationId,
            'call_uuid' => (string) ($metadata['message_uuid'] ?? $metadata['message_id'] ?? $metadata['user'] ?? ''),
            'event_type' => CallEventLog::EVENT_VOICEMAIL_RECEIVED,
            'timestamp' => now()->toIso8601String(),
            'schema_version' => CallEventLog::SCHEMA_VERSION,
            'metadata' => $metadata,
        ];
    }
}
