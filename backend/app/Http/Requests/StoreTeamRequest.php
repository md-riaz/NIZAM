<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organization = $this->route('organization');

        return [
            'schedule_id' => [
                'nullable',
                'uuid',
                function ($attribute, $value, $fail) use ($organization) {
                    if ($value && ! $organization->schedules()->where('id', $value)->where('is_active', true)->exists()) {
                        $fail('The selected schedule is invalid for this organization.');
                    }
                },
            ],
            'holiday_calendar_id' => [
                'nullable',
                'uuid',
                function ($attribute, $value, $fail) use ($organization) {
                    if ($value && ! $organization->holidayCalendars()->where('id', $value)->where('is_active', true)->exists()) {
                        $fail('The selected holiday calendar is invalid for this organization.');
                    }
                },
            ],
            'name' => 'required|string|max:255',
            'strategy' => 'required|string|in:simultaneous,round_robin,priority',
            'timeout' => 'required|integer|min:1|max:300',
            'is_active' => 'boolean',
            'members' => 'nullable|array',
            'members.*.endpoint_type' => 'required|string|max:64',
            'members.*.endpoint_id' => 'required|uuid',
            'members.*.priority' => 'nullable|integer|min:0|max:9999',
            'members.*.is_active' => 'boolean',
        ];
    }

}
