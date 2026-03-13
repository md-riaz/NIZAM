<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHolidayCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'timezone' => 'sometimes|string|max:64',
            'is_active' => 'boolean',
            'holidays' => 'nullable|array',
            'holidays.*.name' => 'required|string|max:255',
            'holidays.*.holiday_date' => 'required|date',
            'holidays.*.is_active' => 'boolean',
        ];
    }
}
