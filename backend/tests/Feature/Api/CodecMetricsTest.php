<?php

namespace Tests\Feature\Api;

use App\Models\CallDetailRecord;
use App\Models\Gateway;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodecMetricsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);
    }

    public function test_returns_codec_distribution(): void
    {
        CallDetailRecord::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'negotiated_codec' => 'PCMU',
        ]);
        CallDetailRecord::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'negotiated_codec' => 'G722',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/tenants/{$this->tenant->id}/codec-metrics");

        $response->assertStatus(200);

        $distribution = $response->json('data.codec_distribution');
        $byCodec = collect($distribution)->keyBy('codec');

        $this->assertEquals(3, $byCodec['PCMU']['count']);
        $this->assertEquals(2, $byCodec['G722']['count']);
    }

    public function test_returns_codec_mismatch_count(): void
    {
        CallDetailRecord::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'read_codec' => 'PCMU',
            'write_codec' => 'G722',
        ]);
        CallDetailRecord::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'read_codec' => 'PCMU',
            'write_codec' => 'PCMU',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/tenants/{$this->tenant->id}/codec-metrics");

        $response->assertStatus(200);
        $response->assertJsonPath('data.codec_mismatch_count', 2);
    }

    public function test_returns_active_gateway_count_and_codec_info(): void
    {
        Gateway::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        Gateway::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/tenants/{$this->tenant->id}/codec-metrics");

        $response->assertStatus(200);
        $response->assertJsonPath('data.active_gateways', 2);
        $response->assertJsonCount(2, 'data.gateways');
    }

    public function test_returns_zero_mismatch_rate_with_no_cdrs(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/tenants/{$this->tenant->id}/codec-metrics");

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('data.codec_mismatch_rate'));
        $response->assertJsonPath('data.codec_mismatch_count', 0);
    }

    public function test_unauthenticated_requests_return_401(): void
    {
        $response = $this->getJson("/api/v1/tenants/{$this->tenant->id}/codec-metrics");

        $response->assertStatus(401);
    }
}
