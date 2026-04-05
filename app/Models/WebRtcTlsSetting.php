<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebRtcTlsSetting extends Model
{
    protected $table = 'webrtc_tls_settings';

    protected $fillable = [
        'webrtc_enabled',
        'active_mode',
        'trusted_ca_enabled',
        'trusted_ca_cert_dir',
        'self_signed_enabled',
        'self_signed_cert_dir',
    ];

    protected function casts(): array
    {
        return [
            'webrtc_enabled' => 'boolean',
            'trusted_ca_enabled' => 'boolean',
            'self_signed_enabled' => 'boolean',
        ];
    }
}
