<?php

namespace Tests\Unit\Models;

use App\Models\Ivr;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IvrTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_created_with_valid_attributes(): void
    {
        $organization = Organization::factory()->create();
        $ivr = Ivr::factory()->create(['organization_id' => $organization->id]);

        $this->assertDatabaseHas('ivrs', [
            'id' => $ivr->id,
            'organization_id' => $organization->id,
        ]);
    }

    public function test_belongs_to_a_organization(): void
    {
        $organization = Organization::factory()->create();
        $ivr = Ivr::factory()->create(['organization_id' => $organization->id]);

        $this->assertInstanceOf(Organization::class, $ivr->organization);
        $this->assertEquals($organization->id, $ivr->organization->id);
    }

    public function test_options_is_cast_to_array(): void
    {
        $options = [
            ['digit' => '1', 'destination_type' => 'extension', 'destination_id' => 'uuid-1'],
            ['digit' => '2', 'destination_type' => 'ring_group', 'destination_id' => 'uuid-2'],
        ];
        $ivr = Ivr::factory()->create(['options' => $options]);

        $this->assertIsArray($ivr->options);
        $this->assertCount(2, $ivr->options);
        $this->assertEquals('1', $ivr->options[0]['digit']);
    }

    public function test_timeout_is_cast_to_integer(): void
    {
        $ivr = Ivr::factory()->create(['timeout' => '5']);

        $this->assertIsInt($ivr->timeout);
        $this->assertEquals(5, $ivr->timeout);
    }

    public function test_max_failures_is_cast_to_integer(): void
    {
        $ivr = Ivr::factory()->create(['max_failures' => '3']);

        $this->assertIsInt($ivr->max_failures);
        $this->assertEquals(3, $ivr->max_failures);
    }

    public function test_is_active_is_cast_to_boolean(): void
    {
        $ivr = Ivr::factory()->create(['is_active' => 1]);

        $this->assertIsBool($ivr->is_active);
        $this->assertTrue($ivr->is_active);
    }

    public function test_uses_ivrs_table(): void
    {
        $ivr = new Ivr;

        $this->assertEquals('ivrs', $ivr->getTable());
    }
}
