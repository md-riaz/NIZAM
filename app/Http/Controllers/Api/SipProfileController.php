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
        return SipProfile::all();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:sip_profiles,name',
            'description' => 'nullable|string',
            'settings' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $profile = SipProfile::create($validated);

        return response()->json($profile, Response::HTTP_CREATED);
    }

    public function show(SipProfile $sipProfile)
    {
        return $sipProfile;
    }

    public function update(Request $request, SipProfile $sipProfile)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|unique:sip_profiles,name,' . $sipProfile->id,
            'description' => 'nullable|string',
            'settings' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $sipProfile->update($validated);

        return $sipProfile;
    }

    public function destroy(SipProfile $sipProfile)
    {
        $sipProfile->delete();

        return response()->noContent();
    }
}
