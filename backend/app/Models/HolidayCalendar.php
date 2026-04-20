<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HolidayCalendar extends Model
{
    use HasFactory, HasUuids;

    public const SCOPE_ORGANIZATION = 'organization';

    public const SCOPE_TEAM = 'team';

    public const SCOPE_USER = 'user';

    protected $fillable = [
        'organization_id',
        'name',
        'timezone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function defaultForOrganization(): HasOne
    {
        return $this->hasOne(Organization::class, 'default_holiday_calendar_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function scopeLabel(): string
    {
        if ($this->relationLoaded('defaultForOrganization') ? $this->defaultForOrganization !== null : $this->defaultForOrganization()->exists()) {
            return self::SCOPE_ORGANIZATION;
        }

        if ($this->relationLoaded('users') ? $this->users->isNotEmpty() : $this->users()->exists()) {
            return self::SCOPE_USER;
        }

        if ($this->relationLoaded('teams') ? $this->teams->isNotEmpty() : $this->teams()->exists()) {
            return self::SCOPE_TEAM;
        }

        return self::SCOPE_ORGANIZATION;
    }
}
