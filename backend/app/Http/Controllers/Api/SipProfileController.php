<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SipProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Manages global FreeSWITCH SIP profiles.
 *
 * SIP profiles are platform-level objects shared by every organization, and
 * saving one restarts the corresponding sofia profile (see SipProfileSetting),
 * which drops registrations for all tenants. Every action is therefore gated on
 * the platform-admin ability rather than on organization-scoped permissions.
 */
class SipProfileController extends Controller
{
    public function index()
    {
        Gate::authorize('platform-admin');

        return SipProfile::with('settings')->orderByDesc('id')->get();
    }

    public function store(Request $request)
    {
        Gate::authorize('platform-admin');

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

        $this->validateWebRtcSettings($validated['settings'] ?? []);

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
        Gate::authorize('platform-admin');

        return $sipProfile->load('settings');
    }

    public function update(Request $request, SipProfile $sipProfile)
    {
        Gate::authorize('platform-admin');

        $validated = $request->validate([
            'name' => 'sometimes|string|unique:sip_profiles,name,'.$sipProfile->id,
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
            'settings_to_delete.*' => 'uuid',
        ]);

        $this->validateWebRtcSettings($validated['settings'] ?? []);

        $sipProfile->update([
            'name' => $validated['name'] ?? $sipProfile->name,
            'hostname' => array_key_exists('hostname', $validated) ? $validated['hostname'] : $sipProfile->hostname,
            'description' => array_key_exists('description', $validated) ? $validated['description'] : $sipProfile->description,
            'is_active' => $validated['is_active'] ?? $sipProfile->is_active,
        ]);

        // Delete requested settings
        if (! empty($validated['settings_to_delete'])) {
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
        Gate::authorize('platform-admin');

        $sipProfile->delete();

        return response()->noContent();
    }

    protected function validateWebRtcSettings(array $settings): void
    {
        $bindings = ['ws-binding', 'wss-binding'];
        $booleans = ['tls', 'tls-only', 'dtls-srtp', 'enable-ice', 'tls-verify-date'];
        $ports = ['tls-sip-port'];

        $indexedSettings = collect($settings)
            ->keyBy(fn (array $setting) => $setting['name'] ?? null)
            ->all();

        $wssEnabled = (bool) ($indexedSettings['wss-binding']['is_enabled'] ?? false);

        foreach ($settings as $setting) {
            $name = $setting['name'] ?? null;
            $value = (string) ($setting['value'] ?? '');
            $isEnabled = (bool) ($setting['is_enabled'] ?? false);

            if ($name === null) {
                continue;
            }

            if (in_array($name, $bindings, true) && ! preg_match('/^:\d+$/', $value)) {
                abort(response()->json([
                    'message' => sprintf('The %s value must be in :port format.', $name),
                    'errors' => [$name => [sprintf('The %s value must be in :port format.', $name)]],
                ], 422));
            }

            if (in_array($name, $booleans, true) && ! in_array($value, ['true', 'false'], true)) {
                abort(response()->json([
                    'message' => sprintf('The %s value must be true or false.', $name),
                    'errors' => [$name => [sprintf('The %s value must be true or false.', $name)]],
                ], 422));
            }

            if (in_array($name, $ports, true) && ! ctype_digit($value)) {
                abort(response()->json([
                    'message' => sprintf('The %s value must be numeric.', $name),
                    'errors' => [$name => [sprintf('The %s value must be numeric.', $name)]],
                ], 422));
            }

            if ($name === 'tls-cert-dir' && $wssEnabled && trim($value) === '') {
                abort(response()->json([
                    'message' => 'The tls-cert-dir value is required when WSS is enabled.',
                    'errors' => ['tls-cert-dir' => ['The tls-cert-dir value is required when WSS is enabled.']],
                ], 422));
            }
        }

        if (! $wssEnabled) {
            return;
        }

        foreach (['tls', 'dtls-srtp'] as $requiredSetting) {
            $setting = $indexedSettings[$requiredSetting] ?? null;
            $value = (string) ($setting['value'] ?? 'false');
            $isEnabled = (bool) ($setting['is_enabled'] ?? false);

            if (! $isEnabled || $value !== 'true') {
                abort(response()->json([
                    'message' => sprintf('The %s setting must be enabled and set to true when WSS is enabled.', $requiredSetting),
                    'errors' => [$requiredSetting => [sprintf('The %s setting must be enabled and set to true when WSS is enabled.', $requiredSetting)]],
                ], 422));
            }
        }

        $tlsCertDir = (string) ($indexedSettings['tls-cert-dir']['value'] ?? '');
        if (trim($tlsCertDir) === '') {
            abort(response()->json([
                'message' => 'The tls-cert-dir value is required when WSS is enabled.',
                'errors' => ['tls-cert-dir' => ['The tls-cert-dir value is required when WSS is enabled.']],
            ], 422));
        }
    }
}
