<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Extension extends Model
{
    use Auditable, HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'extension',
        'password',
        'directory_first_name',
        'directory_last_name',
        'effective_caller_id_name',
        'effective_caller_id_number',
        'outbound_caller_id_name',
        'outbound_caller_id_number',
        'outbound_caller_id_privacy',
        'outbound_caller_id_pai',
        'voicemail_enabled',
        'voicemail_pin',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'voicemail_pin',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'voicemail_pin' => 'encrypted',
            'voicemail_enabled' => 'boolean',
            'outbound_caller_id_pai' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function deviceProfiles(): HasMany
    {
        return $this->hasMany(DeviceProfile::class);
    }

    public function agent(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Agent::class);
    }

    /**
     * Get WebRTC connection parameters for this extension.
     *
     * Reads from system-wide config (config/nizam.php → webrtc section).
     * Returns everything a SIP.js or similar WebRTC client needs to connect.
     *
     * @param  string  $appUrl  The application base URL (used to construct WSS URL)
     * @return array<string, mixed>
     */
    public function getWebRtcConfig(string $appUrl): array
    {
        $webrtcConfig = config('nizam.webrtc');
        $sslSetting = \App\Models\SslSetting::where('is_enabled', true)->where('status', 'active')->first();
        
        $parsedUrl = parse_url($appUrl);
        $host = $parsedUrl['host'] ?? 'localhost';
        
        // If SSL is active for a specific domain, use that domain instead of the app URL host
        if ($sslSetting && !empty($sslSetting->domains)) {
            $host = $sslSetting->domains[0];
        }

        $wssPort = $webrtcConfig['wss_port'] ?? 7443;

        // Build ICE servers array for WebRTC clients
        $iceServers = [];

        if (!empty($webrtcConfig['stun_server'])) {
            $iceServers[] = [
                'urls' => $webrtcConfig['stun_server'],
            ];
        }

        if (!empty($webrtcConfig['turn_server'])) {
            $turnEntry = [
                'urls' => $webrtcConfig['turn_server'],
            ];
            if (!empty($webrtcConfig['turn_username'])) {
                $turnEntry['username'] = $webrtcConfig['turn_username'];
            }
            if (!empty($webrtcConfig['turn_password'])) {
                $turnEntry['credential'] = $webrtcConfig['turn_password'];
            }
            $iceServers[] = $turnEntry;
        }

        return [
            'enabled' => (bool) ($webrtcConfig['enabled'] ?? false),
            'websocket_url' => "wss://{$host}:{$wssPort}",
            'sip_uri' => "sip:{$this->extension}@{$this->tenant->domain}",
            'sip_username' => $this->extension,
            'sip_password' => $this->password,
            'sip_domain' => $this->tenant->domain,
            'display_name' => trim(($this->directory_first_name ?? '').' '.($this->directory_last_name ?? '')),
            'ice_servers' => $iceServers,
            'codec_prefs' => explode(',', $webrtcConfig['codec_prefs'] ?? 'OPUS,PCMU,PCMA,G722'),
        ];
    }
}
