<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * What the ingester has already done with one spooled call detail record.
 *
 * Identified by file name: mod_xml_cdr names each record after the call it
 * describes, so the name is already unique and already its identity.
 */
class ProcessedCdrFile extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_PROCESSED = 'processed';

    /** Attempted and failed, still in the spool, still owed another try. */
    public const STATUS_FAILED = 'failed';

    /** Given up on and moved out of the spool; never retried. */
    public const STATUS_QUARANTINED = 'quarantined';

    protected $fillable = [
        'file_name',
        'file_path',
        'status',
        'attempts',
        'last_attempted_at',
        'call_uuid',
        'error_message',
        'quarantine_reason',
        'quarantine_path',
        'processed_at',
    ];

    /**
     * The database default is not visible on a freshly created model, and the
     * attempt count is read before any refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'attempts' => 0,
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'last_attempted_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * The statuses that mean this record is finished with, one way or another.
     *
     * @return array<int, string>
     */
    public static function settledStatuses(): array
    {
        return [self::STATUS_PROCESSED, self::STATUS_QUARANTINED];
    }
}
