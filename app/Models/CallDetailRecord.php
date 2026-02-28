<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CallDetailRecord extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
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
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the recordings associated with this CDR via call UUID.
     */
    public function recordings(): HasMany
    {
        return $this->hasMany(Recording::class, 'call_uuid', 'uuid');
    }
}
