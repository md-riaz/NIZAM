<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CallDetailRecord extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'uuid',
        'caller_id_name',
        'caller_id_number',
        'destination_number',
        'context',
        'start_stamp',
        'answer_stamp',
        'end_stamp',
        'duration',
        'billsec',
        'hangup_cause',
        'direction',
        'recording_path',
        'read_codec',
        'write_codec',
        'negotiated_codec',
        'mos_score',
        'packet_loss',
        'jitter',
        'latency',
        'quality_score',
        'sip_user_agent',
        'remote_media_ip',
        'call_type',
        'tags',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_stamp' => 'datetime',
            'answer_stamp' => 'datetime',
            'end_stamp' => 'datetime',
            'duration' => 'integer',
            'billsec' => 'integer',
            'mos_score' => 'decimal:2',
            'packet_loss' => 'decimal:2',
            'jitter' => 'integer',
            'latency' => 'integer',
            'quality_score' => 'integer',
            'tags' => 'array',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the enrichment data for this CDR.
     */
    public function enrichment(): HasOne
    {
        return $this->hasOne(CdrEnrichment::class, 'cdr_id');
    }

    /**
     * Get the recordings associated with this CDR via call UUID.
     */
    public function recordings(): HasMany
    {
        return $this->hasMany(Recording::class, 'call_uuid', 'uuid');
    }

    /**
     * The interaction journey for this call, if one was traced.
     *
     * CDRs are written by FreeSWITCH and call sessions by the delivery pipeline,
     * so the two are joined on the channel UUID rather than a foreign key. Not
     * every CDR has a session — inbound calls that never entered the delivery
     * path will not.
     */
    public function callSession(): HasOne
    {
        return $this->hasOne(CallSession::class, 'call_uuid', 'uuid');
    }
}
