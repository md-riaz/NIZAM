<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SipProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SipProfileController extends Controller
{
    public function index()
    {
        return SipProfile::with('settings')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:sip_profiles,name',
            'hostname' => 'nullable|string',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'settings' => 'array',
            'settings.*.name' => 'required|string',
            'settings.*.value' => 'required|string',
            'settings.*.is_enabled' => 'boolean',
            'settings.*.description' => 'nullable|string',
        ]);

        $profile = SipProfile::create([
            'name' => $validated['name'],
            'hostname' => $validated['hostname'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (isset($validated['settings'])) {
            foreach ($validated['settings'] as $setting) {
                $profile->settings()->create($setting);
            }
        }

        return response()->json($profile->load('settings'), Response::HTTP_CREATED);
    }

    public function show(SipProfile $sipProfile)
    {
        return $sipProfile->load('settings');
    }

    public function update(Request $request, SipProfile $sipProfile)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|unique:sip_profiles,name,' . $sipProfile->id,
            'hostname' => 'nullable|string',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'settings' => 'array',
            'settings.*.id' => 'nullable|uuid',
            'settings.*.name' => 'required|string',
            'settings.*.value' => 'required|string',
            'settings.*.is_enabled' => 'boolean',
            'settings.*.description' => 'nullable|string',
            'settings_to_delete' => 'array',
            'settings_to_delete.*' => 'uuid'
        ]);

        $sipProfile->update([
            'name' => $validated['name'] ?? $sipProfile->name,
            'hostname' => array_key_exists('hostname', $validated) ? $validated['hostname'] : $sipProfile->hostname,
            'description' => array_key_exists('description', $validated) ? $validated['description'] : $sipProfile->description,
            'is_active' => $validated['is_active'] ?? $sipProfile->is_active,
        ]);

        // Delete requested settings
        if (!empty($validated['settings_to_delete'])) {
            $sipProfile->settings()->whereIn('id', $validated['settings_to_delete'])->delete();
        }

        // Upsert settings
        if (isset($validated['settings'])) {
            foreach ($validated['settings'] as $settingData) {
                $sipProfile->settings()->updateOrCreate(
                    // If ID is passed or Name is passed depending on your form logic. Use Name to act as composite key.
                    ['name' => $settingData['name']],
                    [
                        'value' => $settingData['value'],
                        'is_enabled' => $settingData['is_enabled'] ?? true,
                        'description' => $settingData['description'] ?? null,
                    ]
                );
            }
        }

        return $sipProfile->load('settings');
    }

    public function destroy(SipProfile $sipProfile)
    {
        $sipProfile->delete();

        return response()->noContent();
    }
}
