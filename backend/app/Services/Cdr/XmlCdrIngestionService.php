<?php

namespace App\Services\Cdr;

use App\Events\CallDetailRecordCreated;
use App\Models\CallDetailRecord;
use App\Models\Organization;
use App\Models\ProcessedCdrFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class XmlCdrIngestionService
{
    public function __construct(
        protected ?XmlCdrFileParser $parser = null,
        protected ?XmlCdrSpool $spool = null,
    ) {
        $this->parser ??= app(XmlCdrFileParser::class);
        $this->spool ??= app(XmlCdrSpool::class);
    }

    public function ingest(string $path): ProcessedCdrFile
    {
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

        // The live event path fills these from the channel and the spool is often
        // the second writer, so an absent value here must not erase what is
        // already stored.
        foreach (['sip_user_agent', 'remote_media_ip'] as $optional) {
            if (! empty($parsed[$optional])) {
                $attributes[$optional] = $parsed[$optional];
            }
        }

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
                'file_name' => basename($path),
            ],
            [
                'file_path' => $path,
                'status' => ProcessedCdrFile::STATUS_PROCESSED,
                'call_uuid' => $cdr->uuid,
                'error_message' => null,
                'quarantine_reason' => null,
                'quarantine_path' => null,
                'last_attempted_at' => now(),
                'processed_at' => now(),
            ]
        );

        // The row is committed, so the file has served its purpose. Deleting only
        // here is what makes the spool safe: anything still on disk is work that
        // has not been acknowledged, whatever happened to this process.
        if ($this->cleanupAfterSuccess() && File::exists($path)) {
            File::delete($path);
        }

        return $processed;
    }

    /**
     * Record a failed attempt, quarantining the file once it is out of tries.
     *
     * A failure is not assumed permanent. An unresolvable domain or an
     * unreachable database is often temporary, and FreeSWITCH will never send the
     * record again, so the file stays in the spool to be retried. Only when the
     * attempt budget is exhausted is it moved out — still readable, still
     * requeueable by hand, but no longer slowing every later pass.
     */
    public function markFailed(string $path, \Throwable $exception): ProcessedCdrFile
    {
        $record = ProcessedCdrFile::query()->firstOrNew(['file_name' => basename($path)]);
        $attempts = (int) ($record->attempts ?? 0) + 1;
        $exhausted = $attempts >= $this->maxAttempts();

        $quarantinePath = null;

        if ($exhausted) {
            $quarantinePath = $this->spool->quarantine($path, $this->reasonFor($exception));
        }

        $record->fill([
            'file_path' => $path,
            'status' => $exhausted ? ProcessedCdrFile::STATUS_QUARANTINED : ProcessedCdrFile::STATUS_FAILED,
            'attempts' => $attempts,
            'last_attempted_at' => now(),
            'call_uuid' => null,
            'error_message' => $exception->getMessage(),
            'quarantine_reason' => $exhausted ? $this->reasonFor($exception) : null,
            'quarantine_path' => $quarantinePath,
            'processed_at' => now(),
        ])->save();

        return $record;
    }

    /**
     * Which quarantine a failure belongs in.
     *
     * The distinction is for whoever inspects the pile later: a record that would
     * not parse is a different problem from one that parsed and could not be
     * stored, and they are worth requeuing under different circumstances.
     */
    protected function reasonFor(\Throwable $exception): string
    {
        return $exception instanceof XmlCdrParseException
            ? XmlCdrSpool::REASON_XML
            : XmlCdrSpool::REASON_SQL;
    }

    protected function maxAttempts(): int
    {
        return max(1, (int) config('telephony.xml_cdr.max_attempts', 3));
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
}
