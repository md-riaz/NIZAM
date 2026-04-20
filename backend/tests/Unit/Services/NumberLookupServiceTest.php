<?php

namespace Tests\Unit\Services;

use App\Models\Organization;
use App\Services\NumberLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NumberLookupServiceTest extends TestCase
{
    use RefreshDatabase;

    private NumberLookupService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NumberLookupService;
    }

    public function test_returns_null_when_no_lookup_url_configured(): void
    {
        $organization = Organization::factory()->create(['settings' => []]);

        $result = $this->service->lookup($organization, '+15551234567');

        $this->assertNull($result);
    }

    public function test_returns_lookup_data_on_successful_response(): void
    {
        Http::fake([
            'https://lookup.example.com/*' => Http::response([
                'name' => 'John Doe',
                'type' => 'mobile',
            ], 200),
        ]);

        $organization = Organization::factory()->create([
            'settings' => ['number_lookup_url' => 'https://lookup.example.com/lookup'],
        ]);

        $result = $this->service->lookup($organization, '+15551234567');

        $this->assertNotNull($result);
        $this->assertEquals('John Doe', $result['name']);
        $this->assertEquals('mobile', $result['type']);

        Http::assertSent(function ($request) use ($organization) {
            return $request->hasHeader('X-Organization-Id', $organization->id)
                && $request['number'] === '+15551234567';
        });
    }

    public function test_returns_null_on_failed_response(): void
    {
        Http::fake([
            'https://lookup.example.com/*' => Http::response(null, 500),
        ]);

        $organization = Organization::factory()->create([
            'settings' => ['number_lookup_url' => 'https://lookup.example.com/lookup'],
        ]);

        $result = $this->service->lookup($organization, '+15551234567');

        $this->assertNull($result);
    }

    public function test_returns_null_on_connection_error(): void
    {
        Http::fake([
            'https://lookup.example.com/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
            },
        ]);

        $organization = Organization::factory()->create([
            'settings' => ['number_lookup_url' => 'https://lookup.example.com/lookup'],
        ]);

        $result = $this->service->lookup($organization, '+15551234567');

        $this->assertNull($result);
    }
}
