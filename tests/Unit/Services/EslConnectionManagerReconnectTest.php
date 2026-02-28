<?php

namespace Tests\Unit\Services;

use App\Services\EslConnectionManager;
use Tests\TestCase;

class EslConnectionManagerReconnectTest extends TestCase
{
    public function test_ensure_connected_returns_false_when_not_connected(): void
    {
        $esl = new EslConnectionManager('127.0.0.1', 0, 'test');

        $this->assertFalse($esl->isConnected());
    }

    public function test_reconnect_returns_false_when_connection_unavailable(): void
    {
        // Use an invalid port so connection always fails
        $esl = new EslConnectionManager('127.0.0.1', 1, 'test');

        $this->assertFalse($esl->reconnect());
    }

    public function test_ensure_connected_attempts_reconnect_when_disconnected(): void
    {
        // Use an invalid port so connection always fails
        $esl = new EslConnectionManager('127.0.0.1', 1, 'test');

        $this->assertFalse($esl->ensureConnected());
    }

    public function test_api_returns_null_when_not_connected(): void
    {
        $esl = new EslConnectionManager('127.0.0.1', 1, 'test');

        $this->assertNull($esl->api('status'));
    }

    public function test_bgapi_returns_null_when_not_connected(): void
    {
        $esl = new EslConnectionManager('127.0.0.1', 1, 'test');

        $this->assertNull($esl->bgapi('status'));
    }
}
