<?php

namespace Tests\Unit\Services;

use App\Models\Schedule;
use App\Models\Tenant;
use App\Services\Schedule\Compile\ScheduleCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleCompilerContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_compiler_uses_tenant_context_for_transfers(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'tenant.example.com']);
        $schedule = Schedule::factory()->create(['tenant_id' => $tenant->id]);
        $schedule->rules()->create(['day_of_week' => '1', 'start_time' => '09:00', 'end_time' => '17:00']);

        $xml = app(ScheduleCompiler::class)->compile($schedule->fresh());

        $this->assertStringContainsString('schedule_'.$schedule->id.'_open XML tenant.example.com', $xml);
        $this->assertStringContainsString('schedule_'.$schedule->id.'_closed XML tenant.example.com', $xml);
        $this->assertStringNotContainsString('XML default', $xml);
    }
}
