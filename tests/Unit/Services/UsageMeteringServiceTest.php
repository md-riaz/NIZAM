<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Models\UsageRecord;
use App\Services\UsageMeteringService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsageMeteringServiceTest extends TestCase
{
    use RefreshDatabase;

    private UsageMeteringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UsageMeteringService;
    }

    public function test_can_record_usage_metric(): void
    {
        $tenant = Tenant::factory()->create();

        $record = $this->service->record(
            $tenant,
            UsageRecord::METRIC_CALL_MINUTES,
            45.5,
        );

        $this->assertDatabaseHas('usage_records', [
            'tenant_id' => $tenant->id,
            'metric' => UsageRecord::METRIC_CALL_MINUTES,
        ]);
        $this->assertEquals(45.5, (float) $record->value);
    }

    public function test_collect_snapshot_records_metrics(): void
    {
        $tenant = Tenant::factory()->create();

        $tenant->extensions()->create([
            'extension' => '1001',
            'password' => 'secret123',
            'is_active' => true,
            'directory_first_name' => 'Test',
            'directory_last_name' => 'User',
        ]);

        $records = $this->service->collectSnapshot($tenant);

        $this->assertCount(3, $records);

        $metrics = collect($records)->pluck('metric')->toArray();
        $this->assertContains(UsageRecord::METRIC_ACTIVE_EXTENSIONS, $metrics);
        $this->assertContains(UsageRecord::METRIC_RECORDING_STORAGE, $metrics);
        $this->assertContains(UsageRecord::METRIC_ACTIVE_DEVICES, $metrics);
    }

    public function test_get_summary_returns_aggregated_data(): void
    {
        $tenant = Tenant::factory()->create();

        $this->service->record($tenant, UsageRecord::METRIC_CALL_MINUTES, 100, null, Carbon::parse('2026-02-10'));
        $this->service->record($tenant, UsageRecord::METRIC_CALL_MINUTES, 200, null, Carbon::parse('2026-02-15'));
        $this->service->record($tenant, UsageRecord::METRIC_CONCURRENT_CALL_PEAK, 10, null, Carbon::parse('2026-02-10'));

        $summary = $this->service->getSummary(
            $tenant,
            Carbon::parse('2026-02-01'),
            Carbon::parse('2026-02-28')
        );

        $this->assertArrayHasKey(UsageRecord::METRIC_CALL_MINUTES, $summary);
        $this->assertEquals(300, $summary[UsageRecord::METRIC_CALL_MINUTES]['total']);
        $this->assertEquals(200, $summary[UsageRecord::METRIC_CALL_MINUTES]['peak']);
        $this->assertEquals(2, $summary[UsageRecord::METRIC_CALL_MINUTES]['count']);

        $this->assertArrayHasKey(UsageRecord::METRIC_CONCURRENT_CALL_PEAK, $summary);
        $this->assertEquals(10, $summary[UsageRecord::METRIC_CONCURRENT_CALL_PEAK]['total']);
    }

    public function test_summary_respects_date_range(): void
    {
        $tenant = Tenant::factory()->create();

        $this->service->record($tenant, UsageRecord::METRIC_CALL_MINUTES, 100, null, Carbon::parse('2026-01-15'));
        $this->service->record($tenant, UsageRecord::METRIC_CALL_MINUTES, 200, null, Carbon::parse('2026-02-15'));

        $summary = $this->service->getSummary(
            $tenant,
            Carbon::parse('2026-02-01'),
            Carbon::parse('2026-02-28')
        );

        $this->assertEquals(200, $summary[UsageRecord::METRIC_CALL_MINUTES]['total']);
        $this->assertEquals(1, $summary[UsageRecord::METRIC_CALL_MINUTES]['count']);
    }
}
