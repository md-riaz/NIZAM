<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcessedCdrFile extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    /** Given up on and moved out of the spool; never retried. */
    public const STATUS_QUARANTINED = 'quarantined';

    protected $fillable = [
        'file_path',
        'file_name',
        'checksum',
        'dedupe_key',
        'status',
        'attempts',
        'last_attempted_at',
        'call_uuid',
        'error_message',
        'quarantine_reason',
        'quarantine_path',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'last_attempted_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public static function dedupeKeyFor(string $filePath, ?string $checksum = null): string
    {
        $normalizedPath = str_replace('\\', '/', trim($filePath));

        return hash('sha256', strtolower($normalizedPath).'|'.($checksum ?? ''));
    }
}
