<?php

namespace App\Services\Cdr;

use App\Events\CallDetailRecordCreated;
use App\Models\CallDetailRecord;
use App\Models\ProcessedCdrFile;
use App\Models\Organization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class XmlCdrIngestionService
{
    public function __construct(
        protected ?XmlCdrFileParser $parser = null,
    ) {
        $this->parser ??= app(XmlCdrFileParser::class);
    }

    public function ingest(string $path): ProcessedCdrFile
    {
        $checksum = $this->checksumFor($path);
        $parsed = $this->parser->parseFile($path);
        $organization = $this->resolveOrganization($parsed);

        if (! $organization) {
            throw new \RuntimeException(sprintf(
                'Unable to resolve organization for XML CDR domain [%s].',
                $parsed['domain'] ?? ''
            ));
        }

        if (empty($parsed['uuid'])) {
            throw new \RuntimeException('XML CDR is missing uuid.');
        }

        $attributes = [
            'organization_id' => $organization->id,
            'caller_id_name' => $parsed['caller_id_name'] ?: null,
            'caller_id_number' => $parsed['caller_id_number'] ?: '',
            'destination_number' => $parsed['destination_number'] ?: '',
            'context' => $parsed['context'] ?: null,
            'start_stamp' => $this->parseTimestamp($parsed['start_stamp']) ?? now(),
            'answer_stamp' => $this->parseTimestamp($parsed['answer_stamp']),
            'end_stamp' => $this->parseTimestamp($parsed['end_stamp']),
            'duration' => $this->durationFor($parsed),
            'billsec' => (int) ($parsed['billsec'] ?? 0),
            'hangup_cause' => $parsed['hangup_cause'] ?: null,
            'direction' => $this->normalizeDirection($parsed['direction'] ?? null),
            'recording_path' => $parsed['recording_path'] ?: null,
            'metadata' => $parsed['metadata'] ?? [],
        ];

        $cdr = CallDetailRecord::query()->firstOrNew([
            'uuid' => $parsed['uuid'],
        ]);

        $wasRecentlyCreated = ! $cdr->exists;
        $cdr->fill($attributes);
        $cdr->save();

        if ($wasRecentlyCreated) {
            CallDetailRecordCreated::dispatch($cdr);
        }

        $processed = ProcessedCdrFile::query()->updateOrCreate(
            [
                'dedupe_key' => ProcessedCdrFile::dedupeKeyFor($path, $checksum),
            ],
            [
                'file_path' => $path,
                'file_name' => basename($path),
                'checksum' => $checksum,
                'status' => ProcessedCdrFile::STATUS_PROCESSED,
                'call_uuid' => $cdr->uuid,
                'error_message' => null,
                'processed_at' => now(),
            ]
        );

        if ($this->cleanupAfterSuccess() && File::exists($path)) {
            File::delete($path);
        }

        return $processed;
    }

    public function markFailed(string $path, \Throwable $exception): ProcessedCdrFile
    {
        $checksum = $this->checksumFor($path);

        return ProcessedCdrFile::query()->updateOrCreate(
            [
                'dedupe_key' => ProcessedCdrFile::dedupeKeyFor($path, $checksum),
            ],
            [
                'file_path' => $path,
                'file_name' => basename($path),
                'checksum' => $checksum,
                'status' => ProcessedCdrFile::STATUS_FAILED,
                'call_uuid' => null,
                'error_message' => $exception->getMessage(),
                'processed_at' => now(),
            ]
        );
    }

    protected function resolveOrganization(array $parsed): ?Organization
    {
        $domain = trim((string) ($parsed['domain'] ?? ''));

        if ($domain === '') {
            return null;
        }

        return Organization::query()->where('domain', $domain)->first();
    }

    protected function parseTimestamp(?string $value): ?Carbon
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    protected function durationFor(array $parsed): int
    {
        $start = $this->parseTimestamp($parsed['start_stamp'] ?? null);
        $end = $this->parseTimestamp($parsed['end_stamp'] ?? null);

        if (! $start || ! $end) {
            return max(0, (int) ($parsed['billsec'] ?? 0));
        }

        return max(0, $start->diffInSeconds($end));
    }

    protected function normalizeDirection(?string $direction): string
    {
        return match ($direction) {
            'inbound', 'outbound', 'local' => $direction,
            default => 'local',
        };
    }

    protected function cleanupAfterSuccess(): bool
    {
        return (bool) config(
            'telephony.xml_cdr.cleanup_after_ingest',
            config('telephony.xml_cdr.cleanup_on_success', true)
        );
    }

    protected function checksumFor(string $path): ?string
    {
        if (! File::exists($path)) {
            return null;
        }

        $checksum = @hash_file('sha256', $path);

        return $checksum === false ? null : $checksum;
    }
}
