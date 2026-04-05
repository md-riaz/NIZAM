<?php

namespace Tests\Unit\Services;

use App\Models\WebRtcTlsSetting;
use App\Services\WebRtcTlsSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebRtcTlsSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_default_modes_when_database_is_empty(): void
    {
        config()->set('telephony.webrtc.enabled', true);
        config()->set('telephony.webrtc.dtls_cert_dir', '/usr/local/freeswitch/certs');

        $service = app(WebRtcTlsSettingsService::class);
        $settings = $service->getSettings();

        $this->assertTrue($settings['webrtc_enabled']);
        $this->assertSame('trusted_ca', $settings['active_mode']);
        $this->assertSame('/usr/local/freeswitch/certs', $settings['modes']['trusted_ca']['cert_dir']);
        $this->assertTrue($settings['modes']['self_signed']['enabled']);
    }

    public function test_profile_overrides_use_active_certificate_directory(): void
    {
        WebRtcTlsSetting::query()->create([
            'webrtc_enabled' => true,
            'active_mode' => 'self_signed',
            'trusted_ca_enabled' => true,
            'trusted_ca_cert_dir' => '/secure/certs/trusted',
            'self_signed_enabled' => true,
            'self_signed_cert_dir' => '/secure/certs/dev',
        ]);

        $service = app(WebRtcTlsSettingsService::class);
        $overrides = $service->profileOverrides();

        $this->assertSame('/secure/certs/dev', $overrides['tls-cert-dir']);
        $this->assertSame('true', $overrides['dtls-srtp']);
        $this->assertSame(':5066', $overrides['ws-binding']);
        $this->assertSame(':7443', $overrides['wss-binding']);
    }
}
