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

    protected $fillable = [
        'file_path',
        'file_name',
        'checksum',
        'dedupe_key',
        'status',
        'call_uuid',
        'error_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    public static function dedupeKeyFor(string $filePath, ?string $checksum = null): string
    {
        $normalizedPath = str_replace('\\', '/', trim($filePath));

        return hash('sha256', strtolower($normalizedPath).'|'.($checksum ?? ''));
    }
}
