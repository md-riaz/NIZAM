<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recording extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'tenant_id',
        'call_uuid',
        'file_path',
        'file_name',
        'file_size',
        'format',
        'duration',
        'direction',
        'caller_id_number',
        'destination_number',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'duration' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the CDR associated with this recording via call UUID.
     */
    public function cdr(): BelongsTo
    {
        return $this->belongsTo(CallDetailRecord::class, 'call_uuid', 'uuid');
    }
}
