<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organization = $this->route('organization');
        $did = $this->route('did');

        return [
            'number' => [
                'required',
                'string',
                function ($attribute, $value, $fail) use ($organization, $did) {
                    $query = $organization->dids()->where('number', $value);
                    if ($did) {
                        $didId = $did instanceof \App\Models\Did ? $did->id : $did;
                        $query->where('id', '!=', $didId);
                    }
                    if ($query->exists()) {
                        $fail('The DID number has already been taken for this organization.');
                    }
                },
            ],
            'description' => 'nullable|string',
            'destination_type' => 'required|in:extension,ring_group,ivr,voicemail,time_condition,call_routing_policy,flow,bridge',
            'destination_id' => 'required|uuid',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer',
            'team_ids' => 'nullable|array',
            'team_ids.*' => 'uuid',
            'device_profile_ids' => 'nullable|array',
            'device_profile_ids.*' => 'uuid',
            'is_active' => 'boolean',
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            $organization = $this->route('organization');

            foreach (($this->input('user_ids') ?? []) as $userId) {
                if (! $organization->users()->whereKey($userId)->exists()) {
                    $validator->errors()->add('user_ids', 'Selected user must belong to this organization.');
                    break;
                }
            }

            foreach (($this->input('team_ids') ?? []) as $teamId) {
                if (! $organization->teams()->whereKey($teamId)->exists()) {
                    $validator->errors()->add('team_ids', 'Selected team must belong to this organization.');
                    break;
                }
            }

            foreach (($this->input('device_profile_ids') ?? []) as $deviceProfileId) {
                if (! $organization->deviceProfiles()->whereKey($deviceProfileId)->exists()) {
                    $validator->errors()->add('device_profile_ids', 'Selected device must belong to this organization.');
                    break;
                }
            }
        }];
    }
}
