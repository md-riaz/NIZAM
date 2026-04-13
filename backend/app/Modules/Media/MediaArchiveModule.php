<?php

namespace App\Modules\Media;

use App\Models\CallDetailRecord;
use App\Models\Recording;
use App\Modules\BaseModule;
use App\Services\Storage\StorageDriver;
use Illuminate\Support\Carbon;

class MediaArchiveModule extends BaseModule
{
    public function __construct(
        protected ?StorageDriver $storageDriver = null,
    ) {}

    public function name(): string
    {
        return 'media-archive';
    }

    public function description(): string
    {
        return 'Archives call recordings locally with metadata-friendly lifecycle hooks.';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function register(): void
    {
        $this->storageDriver ??= app(StorageDriver::class);
    }

    public function subscribedEvents(): array
    {
        return ['call.end'];
    }

    public function permissions(): array
    {
        return [
            'recordings.view',
            'recordings.download',
            'recordings.delete',
        ];
    }

    public function handleEvent(string $eventType, array $data): void
    {
        if ($eventType !== 'call.end') {
            return;
        }

        $this->archiveFromCallEnd($data);
    }

    /**
     * @return Recording|null
     */
    public function archiveFromCallEnd(array $payload): ?Recording
    {
        $tenantId = (string) ($payload['tenant_id'] ?? '');
        $callUuid = (string) ($payload['call_uuid'] ?? $payload['uuid'] ?? '');
        $sourcePath = $this->normalizeSourcePath($payload['source_path'] ?? $payload['recording_path'] ?? null);

        if (isset($payload['metadata']) && is_array($payload['metadata'])) {
            $payload['metadata']['recording_path'] = $sourcePath;
            $payload['metadata']['storage_path'] = $this->canonicalizeStoragePath($payload['metadata']['storage_path'] ?? $sourcePath);
            $payload['metadata']['storage_reference'] = $this->canonicalizeStoragePath($payload['metadata']['storage_reference'] ?? $payload['metadata']['storage_path']);
        }

        if ($tenantId === '' || $callUuid === '' || $sourcePath === null || ! is_file($sourcePath)) {
            return null;
        }

        $existing = Recording::query()
            ->where('tenant_id', $tenantId)
            ->where('call_uuid', $callUuid)
            ->first();

        if ($existing !== null && $existing->archived_at !== null) {
            return $existing;
        }

        $destinationPath = $this->buildArchivePath($tenantId, $callUuid, $sourcePath, $payload);
        $archive = $this->storageDriver()->archive($sourcePath, $destinationPath);
        $now = now();

        $recording = Recording::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'call_uuid' => $callUuid,
            ],
            [
                'file_path' => $archive['path'],
                'file_name' => basename($archive['path']),
                'file_size' => $archive['size'] ?? ($payload['file_size'] ?? 0),
                'format' => $this->detectFormat($archive['path'], $payload),
                'duration' => $this->nullableInt($payload['duration'] ?? $payload['billsec'] ?? null),
                'direction' => $payload['direction'] ?? null,
                'caller_id_number' => $payload['caller_id_number'] ?? null,
                'destination_number' => $payload['destination_number'] ?? null,
                'storage_driver' => 'local',
                'storage_reference' => $this->canonicalizeStoragePath($archive['path']),
                'archived_at' => $now,
                'archive_metadata' => $this->buildArchiveMetadata($payload, $archive, $sourcePath, $destinationPath, $now),
            ],
        );

        $this->syncCdrMetadata($recording, $archive, $payload, $now);

        return $recording->fresh();
    }

    protected function syncCdrMetadata(Recording $recording, array $archive, array $payload, Carbon $archivedAt): void
    {
        $cdr = CallDetailRecord::query()
            ->where('tenant_id', $recording->tenant_id)
            ->where('uuid', $recording->call_uuid)
            ->first();

        if ($cdr === null) {
            return;
        }

        $metadata = $cdr->metadata ?? [];
        $canonicalArchivePath = $this->canonicalizeStoragePath($archive['path']);

        $metadata['recording_archive'] = [
            'storage_driver' => 'local',
            'storage_path' => $canonicalArchivePath,
            'storage_reference' => $canonicalArchivePath,
            'archived_at' => $archivedAt->toIso8601String(),
            'file_size' => $archive['size'],
            'mime_type' => $archive['mime_type'],
            'last_modified' => $archive['last_modified'],
        ];

        $cdr->forceFill([
            'recording_path' => $canonicalArchivePath,
            'metadata' => $metadata,
        ])->save();
    }

    protected function buildArchivePath(string $tenantId, string $callUuid, string $sourcePath, array $payload): string
    {
        $timestamp = $payload['ended_at'] ?? $payload['end_stamp'] ?? now()->toIso8601String();
        $archivedAt = Carbon::parse($timestamp);
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $extension = $extension !== '' ? $extension : strtolower((string) ($payload['format'] ?? 'wav'));

        return sprintf(
            '%s/%s/%s/%s.%s',
            trim((string) config('filesystems.archive.recordings_prefix', 'archive/recordings'), '/'),
            $tenantId,
            $archivedAt->format('Y/m/d'),
            $callUuid,
            $extension
        );
    }

    protected function buildArchiveMetadata(array $payload, array $archive, string $sourcePath, string $destinationPath, Carbon $archivedAt): array
    {
        $canonicalArchivePath = $this->canonicalizeStoragePath($archive['path']);
        $canonicalDestinationPath = $this->canonicalizeStoragePath($destinationPath);

        return [
            'source_path' => $sourcePath,
            'storage_driver' => 'local',
            'storage_path' => $canonicalArchivePath,
            'storage_reference' => $canonicalArchivePath,
            'archived_at' => $archivedAt->toIso8601String(),
            'mime_type' => $archive['mime_type'],
            'last_modified' => $archive['last_modified'],
            'relative_archive_path' => $canonicalDestinationPath,
            'source_exists_after_archive' => is_file($sourcePath),
            'call_metadata' => array_filter([
                'hangup_cause' => $payload['hangup_cause'] ?? null,
                'direction' => $payload['direction'] ?? null,
                'caller_id_number' => $payload['caller_id_number'] ?? null,
                'destination_number' => $payload['destination_number'] ?? null,
            ], fn ($value) => $value !== null),
        ];
    }

    protected function detectFormat(string $path, array $payload): string
    {
        $format = strtolower((string) ($payload['format'] ?? pathinfo($path, PATHINFO_EXTENSION) ?: 'wav'));

        return $format === '' ? 'wav' : $format;
    }

    protected function normalizeSourcePath(mixed $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $normalized = trim(str_replace('\\', '/', $path));
        $normalized = preg_replace('#/+#', '/', $normalized) ?? $normalized;

        return $normalized === '' ? null : $normalized;
    }

    protected function canonicalizeStoragePath(?string $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $normalized = $this->normalizeSourcePath($path);

        if ($normalized === null) {
            return null;
        }

        if (preg_match('/^[A-Za-z]:\//', $normalized) === 1) {
            return ltrim(substr($normalized, 2), '/');
        }

        return ltrim($normalized, '/');
    }

    protected function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    protected function storageDriver(): StorageDriver
    {
        $this->storageDriver ??= app(StorageDriver::class);

        return $this->storageDriver;
    }
}
