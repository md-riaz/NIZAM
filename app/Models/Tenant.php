<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'domain',
        'slug',
        'settings',
        'max_extensions',
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
            'settings' => 'array',
            'max_extensions' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(Extension::class);
    }

    public function dids(): HasMany
    {
        return $this->hasMany(Did::class);
    }

    public function ringGroups(): HasMany
    {
        return $this->hasMany(RingGroup::class);
    }

    public function ivrs(): HasMany
    {
        return $this->hasMany(Ivr::class);
    }

    public function timeConditions(): HasMany
    {
        return $this->hasMany(TimeCondition::class);
    }

    public function cdrs(): HasMany
    {
        return $this->hasMany(CallDetailRecord::class);
    }

    public function deviceProfiles(): HasMany
    {
        return $this->hasMany(DeviceProfile::class);
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(Webhook::class);
    }
}
