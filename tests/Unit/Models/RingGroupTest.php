<?php

namespace Tests\Unit\Models;

use App\Models\RingGroup;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RingGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_created_with_valid_attributes(): void
    {
        $tenant = Tenant::factory()->create();
        $ringGroup = RingGroup::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertDatabaseHas('ring_groups', [
            'id' => $ringGroup->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_belongs_to_a_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $ringGroup = RingGroup::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertInstanceOf(Tenant::class, $ringGroup->tenant);
        $this->assertEquals($tenant->id, $ringGroup->tenant->id);
    }

    public function test_members_is_cast_to_array(): void
    {
        $ringGroup = RingGroup::factory()->create([
            'members' => ['uuid-1', 'uuid-2'],
        ]);

        $this->assertIsArray($ringGroup->members);
        $this->assertCount(2, $ringGroup->members);
    }

    public function test_ring_timeout_is_cast_to_integer(): void
    {
        $ringGroup = RingGroup::factory()->create(['ring_timeout' => '30']);

        $this->assertIsInt($ringGroup->ring_timeout);
        $this->assertEquals(30, $ringGroup->ring_timeout);
    }

    public function test_is_active_is_cast_to_boolean(): void
    {
        $ringGroup = RingGroup::factory()->create(['is_active' => 1]);

        $this->assertIsBool($ringGroup->is_active);
        $this->assertTrue($ringGroup->is_active);
    }

    public function test_has_correct_fillable_attributes(): void
    {
        $ringGroup = new RingGroup;
        $expected = ['tenant_id', 'name', 'strategy', 'ring_timeout', 'members', 'fallback_destination_type', 'fallback_destination_id', 'is_active'];

        $this->assertEquals($expected, $ringGroup->getFillable());
    }
}
