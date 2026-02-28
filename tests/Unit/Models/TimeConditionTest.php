<?php

namespace Tests\Unit\Models;

use App\Models\Tenant;
use App\Models\TimeCondition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeConditionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_created_with_valid_attributes(): void
    {
        $tenant = Tenant::factory()->create();
        $tc = TimeCondition::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertDatabaseHas('time_conditions', [
            'id' => $tc->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_belongs_to_a_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $tc = TimeCondition::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertInstanceOf(Tenant::class, $tc->tenant);
        $this->assertEquals($tenant->id, $tc->tenant->id);
    }

    public function test_conditions_is_cast_to_array(): void
    {
        $conditions = [
            ['wday' => 'mon-fri', 'time_from' => '09:00', 'time_to' => '17:00'],
        ];
        $tc = TimeCondition::factory()->create(['conditions' => $conditions]);

        $this->assertIsArray($tc->conditions);
        $this->assertEquals('mon-fri', $tc->conditions[0]['wday']);
    }

    public function test_is_active_is_cast_to_boolean(): void
    {
        $tc = TimeCondition::factory()->create(['is_active' => 1]);

        $this->assertIsBool($tc->is_active);
        $this->assertTrue($tc->is_active);
    }

    public function test_has_correct_fillable_attributes(): void
    {
        $tc = new TimeCondition;
        $expected = [
            'tenant_id', 'name', 'conditions',
            'match_destination_type', 'match_destination_id',
            'no_match_destination_type', 'no_match_destination_id',
            'is_active',
        ];

        $this->assertEquals($expected, $tc->getFillable());
    }
}
