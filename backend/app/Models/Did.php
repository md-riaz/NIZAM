<?php

namespace App\Models;

use App\Services\DidNormalizationService;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;

class Did extends Model
{
    use Auditable, HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'gateway_id',
        'number',
        'normalized_number',
        'description',
        'destination_type',
        'destination_id',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        Relation::morphMap([
            'extension' => Extension::class,
            'ring_group' => RingGroup::class,
            'ivr' => Ivr::class,
            'voicemail' => Extension::class,
            'time_condition' => TimeCondition::class,
            'call_routing_policy' => CallRoutingPolicy::class,
            'flow' => Flow::class,
            'bridge' => Bridge::class,
        ]);

        static::saving(function (Did $did): void {
            if ($did->number) {
                $defaultCountryCode = (string) data_get($did->tenant?->settings, 'default_country_code', '1');
                $did->normalized_number = DidNormalizationService::toE164($did->number, $defaultCountryCode);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function destination(): MorphTo
    {
        return $this->morphTo(name: 'destination', type: 'destination_type', id: 'destination_id');
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class);
    }

}
