<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id',
        'schedule_id',
        'holiday_calendar_id',
        'name',
        'strategy',
        'timeout',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'timeout' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function holidayCalendar(): BelongsTo
    {
        return $this->belongsTo(HolidayCalendar::class);
    }

    public function effectiveSchedule(): ?Schedule
    {
        return $this->schedule ?: $this->organization?->defaultSchedule;
    }

    public function effectiveHolidayCalendar(): ?HolidayCalendar
    {
        return $this->holidayCalendar ?: $this->effectiveSchedule()?->holidayCalendar ?: $this->organization?->defaultHolidayCalendar;
    }

    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

}
