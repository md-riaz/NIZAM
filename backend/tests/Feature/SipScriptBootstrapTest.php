<?php

namespace Tests\Feature;

use Tests\TestCase;

class SipScriptBootstrapTest extends TestCase
{
    /**
     * Verify that rtckit/php-sip dependency is available.
     */
    public function test_sip_message_class_exists(): void
    {
        $this->assertTrue(class_exists(\RTCKit\SIP\Message::class), 'RTCKit\SIP\Message class should be available.');
    }
}
