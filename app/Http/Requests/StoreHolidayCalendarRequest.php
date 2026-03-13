<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHolidayCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'timezone' => 'required|string|max:64',
            'is_active' => 'boolean',
            'holidays' => 'nullable|array',
            'holidays.*.name' => 'required|string|max:255',
            'holidays.*.holiday_date' => 'required|date',
            'holidays.*.is_active' => 'boolean',
        ];
    }
}
