<?php

namespace Tests\Unit\Models;

use App\Models\CallDetailRecord;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallDetailRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_created_with_valid_attributes(): void
    {
        $tenant = Tenant::factory()->create();
        $cdr = CallDetailRecord::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertDatabaseHas('call_detail_records', [
            'id' => $cdr->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_belongs_to_a_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $cdr = CallDetailRecord::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertInstanceOf(Tenant::class, $cdr->tenant);
        $this->assertEquals($tenant->id, $cdr->tenant->id);
    }

    public function test_timestamps_are_cast_to_datetime(): void
    {
        $cdr = CallDetailRecord::factory()->create([
            'start_stamp' => '2026-01-15 10:00:00',
            'answer_stamp' => '2026-01-15 10:00:05',
            'end_stamp' => '2026-01-15 10:05:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $cdr->start_stamp);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $cdr->answer_stamp);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $cdr->end_stamp);
    }

    public function test_duration_is_cast_to_integer(): void
    {
        $cdr = CallDetailRecord::factory()->create(['duration' => '120']);

        $this->assertIsInt($cdr->duration);
        $this->assertEquals(120, $cdr->duration);
    }

    public function test_billsec_is_cast_to_integer(): void
    {
        $cdr = CallDetailRecord::factory()->create(['billsec' => '90']);

        $this->assertIsInt($cdr->billsec);
        $this->assertEquals(90, $cdr->billsec);
    }

    public function test_has_correct_fillable_attributes(): void
    {
        $cdr = new CallDetailRecord;
        $expected = [
            'tenant_id', 'uuid', 'caller_id_name', 'caller_id_number',
            'destination_number', 'context', 'start_stamp', 'answer_stamp',
            'end_stamp', 'duration', 'billsec', 'hangup_cause', 'direction',
            'recording_path',
        ];

        $this->assertEquals($expected, $cdr->getFillable());
    }
}
