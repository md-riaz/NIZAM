<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_json(): void
    {
        $response = $this->getJson('/api/v1/health');

        // It should return either 200 (healthy) or 503 (degraded) with JSON
        $this->assertContains($response->status(), [200, 503]);
        $response->assertJsonStructure([
            'status',
            'checks' => [
                'app' => ['status'],
                'esl',
            ],
        ]);
    }

    public function test_health_endpoint_app_check_is_always_ok(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertJsonPath('checks.app.status', 'ok');
    }

    public function test_health_endpoint_does_not_require_authentication(): void
    {
        // No actingAs, no token
        $response = $this->getJson('/api/v1/health');

        $this->assertContains($response->status(), [200, 503]);
    }

    public function test_health_endpoint_esl_reports_connection_status(): void
    {
        $response = $this->getJson('/api/v1/health');

        $data = $response->json();
        $this->assertArrayHasKey('connected', $data['checks']['esl']);
    }
}
