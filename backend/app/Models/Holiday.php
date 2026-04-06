<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Holiday extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'holiday_calendar_id',
        'name',
        'holiday_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function holidayCalendar(): BelongsTo
    {
        return $this->belongsTo(HolidayCalendar::class);
    }
}
