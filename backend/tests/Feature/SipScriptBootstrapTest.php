<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SipScriptBootstrapTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify that rtckit/php-sip dependency is available.
     */
    public function test_sip_message_class_exists(): void
    {
        $this->assertTrue(class_exists(\RTCKit\SIP\Message::class), 'RTCKit\SIP\Message class should be available.');
    }

    public function test_bootstrap_can_resolve_extension_credentials_and_target_defaults(): void
    {
        $tenant = \App\Models\Tenant::create([
            'name' => 'SIP Tenant',
            'domain' => 'app.local',
            'slug' => 'sip-tenant',
            'is_active' => true,
        ]);

        $extension = $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'Nzm1001!',
            'directory_first_name' => 'Fatima',
            'directory_last_name' => 'Rahman',
            'is_active' => true,
        ]);

        $profile = \App\Models\SipProfile::create([
            'name' => 'internal',
            'hostname' => null,
            'description' => 'Internal',
            'is_active' => true,
        ]);

        $profile->settings()->create([
            'name' => 'sip-port',
            'value' => '5060',
            'is_enabled' => true,
        ]);

        require base_path('scripts/sip/bootstrap.php');

        $data = sip_test_resolve_extension('1001');

        $this->assertSame('1001', $data['extension']);
        $this->assertSame('Nzm1001!', $data['password']);
        $this->assertSame('app.local', $data['domain']);
        $this->assertSame('5060', $data['internal_port']);
    }
}
