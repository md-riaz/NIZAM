<?php

use App\Models\SipProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $internalProfile = SipProfile::query()->where('name', 'internal')->first();

        if (! $internalProfile) {
            return;
        }

        $webrtcConfig = config('telephony.webrtc');
        $legacy = DB::table('webrtc_tls_settings')->first();
        $legacyEnabled = (bool) ($legacy->webrtc_enabled ?? false);
        $activeMode = (string) ($legacy->active_mode ?? 'trusted_ca');
        $certDir = match ($activeMode) {
            'self_signed' => $legacy->self_signed_cert_dir ?? null,
            default => $legacy->trusted_ca_cert_dir ?? null,
        };
        $certDir ??= (string) ($webrtcConfig['dtls_cert_dir'] ?? '/usr/local/freeswitch/certs');
        $wssPort = (string) ($webrtcConfig['wss_port'] ?? 7443);

        $defaults = [
            'ws-binding' => [':5066', $legacyEnabled],
            'wss-binding' => [':'.$wssPort, $legacyEnabled],
            'tls' => ['true', $legacyEnabled],
            'tls-only' => ['false', false],
            'tls-bind-params' => ['transport=wss', $legacyEnabled],
            'tls-sip-port' => [$wssPort, $legacyEnabled],
            'tls-cert-dir' => [$certDir, $legacyEnabled],
            'tls-version' => ['tlsv1.2', $legacyEnabled],
            'tls-verify-date' => ['true', $legacyEnabled],
            'tls-verify-policy' => ['none', $legacyEnabled],
            'tls-verify-depth' => ['2', $legacyEnabled],
            'dtls-srtp' => ['true', $legacyEnabled],
            'dtls-verify-policy' => ['fingerprint', $legacyEnabled],
            'enable-ice' => ['true', $legacyEnabled],
        ];

        foreach ($defaults as $name => [$value, $enabled]) {
            $existing = DB::table('sip_profile_settings')
                ->where('sip_profile_id', $internalProfile->id)
                ->where('name', $name)
                ->first();

            if ($existing) {
                if ($legacyEnabled && in_array($name, ['ws-binding', 'wss-binding', 'tls', 'tls-bind-params', 'tls-sip-port', 'tls-cert-dir', 'tls-version', 'tls-verify-date', 'tls-verify-policy', 'tls-verify-depth', 'dtls-srtp', 'dtls-verify-policy', 'enable-ice'], true)) {
                    DB::table('sip_profile_settings')
                        ->where('id', $existing->id)
                        ->update([
                            'value' => $name === 'tls-cert-dir' && filled($existing->value) ? $existing->value : $value,
                            'is_enabled' => true,
                            'updated_at' => now(),
                        ]);
                }

                continue;
            }

            DB::table('sip_profile_settings')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'sip_profile_id' => $internalProfile->id,
                'name' => $name,
                'value' => $value,
                'description' => 'Seeded WebRTC transport setting for the internal SIP profile.',
                'is_enabled' => $enabled,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $internalProfile = SipProfile::query()->where('name', 'internal')->first();

        if (! $internalProfile) {
            return;
        }

        DB::table('sip_profile_settings')
            ->where('sip_profile_id', $internalProfile->id)
            ->whereIn('name', [
                'ws-binding',
                'wss-binding',
                'tls-bind-params',
                'tls-sip-port',
                'tls-cert-dir',
                'tls-version',
                'tls-verify-date',
                'tls-verify-policy',
                'tls-verify-depth',
                'dtls-srtp',
                'dtls-verify-policy',
                'enable-ice',
            ])
            ->delete();
    }
};
