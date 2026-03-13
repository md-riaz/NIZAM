<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'holiday_calendar_id' => 'nullable|uuid|exists:holiday_calendars,id',
            'name' => 'required|string|max:255',
            'timezone' => 'required|string|max:64',
            'is_active' => 'boolean',
            'rules' => 'nullable|array',
            'rules.*.day_of_week' => 'required|integer|min:0|max:6',
            'rules.*.start_time' => 'required|date_format:H:i',
            'rules.*.end_time' => 'required|date_format:H:i',
            'breaks' => 'nullable|array',
            'breaks.*.day_of_week' => 'required|integer|min:0|max:6',
            'breaks.*.start_time' => 'required|date_format:H:i',
            'breaks.*.end_time' => 'required|date_format:H:i',
            'exceptions' => 'nullable|array',
            'exceptions.*.start_datetime' => 'required|date',
            'exceptions.*.end_datetime' => 'required|date|after_or_equal:exceptions.*.start_datetime',
            'exceptions.*.state' => 'required|string|in:holiday,exception,break,open,closed',
        ];
    }
}
