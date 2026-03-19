<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CdrEnrichment extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'cdr_id',
        'destination_country',
        'destination_city',
        'carrier_name',
        'number_type',
        'geolocation',
        'enriched_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'geolocation' => 'array',
            'enriched_at' => 'datetime',
        ];
    }

    /**
     * Get the CDR that this enrichment belongs to.
     */
    public function cdr(): BelongsTo
    {
        return $this->belongsTo(CallDetailRecord::class, 'cdr_id');
    }
}
