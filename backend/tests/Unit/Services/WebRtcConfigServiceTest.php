<?php

namespace Tests\Unit\Services;

use App\Models\Extension;
use App\Models\SipProfile;
use App\Models\Tenant;
use App\Services\WebRtcConfigService;
use Database\Seeders\SipProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

use function PHPUnit\Framework\assertNotNull;

class WebRtcConfigServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_disabled_when_internal_profile_webrtc_settings_are_disabled(): void
    {
        $this->seed(SipProfileSeeder::class);

        $tenant = Tenant::query()->create([
            'name' => 'Tenant A',
            'domain' => 'tenant-a.example.com',
            'slug' => 'tenant-a',
            'is_active' => true,
        ]);

        $extension = Extension::query()->create([
            'tenant_id' => $tenant->id,
            'extension' => '1001',
            'password' => 'secret1234',
            'directory_first_name' => 'Test',
            'directory_last_name' => 'User',
            'is_active' => true,
        ]);

        $config = app(WebRtcConfigService::class)->forExtension($extension->load('tenant'), 'https://app.example.com');

        $this->assertFalse($config['enabled']);
    }

    public function test_reads_wss_port_from_internal_profile_settings(): void
    {
        $this->seed(SipProfileSeeder::class);

        $tenant = Tenant::query()->create([
            'name' => 'Tenant B',
            'domain' => 'tenant-b.example.com',
            'slug' => 'tenant-b',
            'is_active' => true,
        ]);

        $extension = Extension::query()->create([
            'tenant_id' => $tenant->id,
            'extension' => '1002',
            'password' => 'secret5678',
            'directory_first_name' => 'Demo',
            'directory_last_name' => 'User',
            'is_active' => true,
        ]);

        $profile = SipProfile::query()->where('name', 'internal')->first();
        assertNotNull($profile);
        $profile->settings()->updateOrCreate(['name' => 'wss-binding'], ['value' => ':7445', 'is_enabled' => true]);
        $profile->settings()->updateOrCreate(['name' => 'dtls-srtp'], ['value' => 'true', 'is_enabled' => true]);
        $profile->settings()->updateOrCreate(['name' => 'enable-ice'], ['value' => 'true', 'is_enabled' => true]);
        $profile->settings()->updateOrCreate(['name' => 'tls-cert-dir'], ['value' => '/secure/certs/dev', 'is_enabled' => true]);

        $config = app(WebRtcConfigService::class)->forExtension($extension->load('tenant'), 'https://app.example.com');

        $this->assertTrue($config['enabled']);
        $this->assertSame('wss://app.example.com:7445', $config['websocket_url']);
        $this->assertSame('/secure/certs/dev', $config['transport']['tls_cert_dir']);
    }
}
