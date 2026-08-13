<?php

namespace Tests\Unit\Models;

use App\Models\Did;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DidTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_created_with_valid_attributes(): void
    {
        $organization = Organization::factory()->create();
        $did = Did::factory()->create(['organization_id' => $organization->id]);

        $this->assertDatabaseHas('dids', [
            'id' => $did->id,
            'organization_id' => $organization->id,
        ]);
    }

    public function test_belongs_to_a_organization(): void
    {
        $organization = Organization::factory()->create();
        $did = Did::factory()->create(['organization_id' => $organization->id]);

        $this->assertInstanceOf(Organization::class, $did->organization);
        $this->assertEquals($organization->id, $did->organization->id);
    }

    public function test_is_active_is_cast_to_boolean(): void
    {
        $did = Did::factory()->create(['is_active' => 1]);

        $this->assertIsBool($did->is_active);
        $this->assertTrue($did->is_active);
    }

    public function test_has_correct_fillable_attributes(): void
    {
        $did = new Did;
        $expected = ['organization_id', 'gateway_id', 'number', 'normalized_number', 'description', 'recording_policy', 'destination_type', 'destination_id', 'is_active'];

        $this->assertEquals($expected, $did->getFillable());
    }

    public function test_supports_polymorphic_destination(): void
    {
        $did = Did::factory()->create([
            'destination_type' => 'extension',
            'destination_id' => \Illuminate\Support\Str::uuid()->toString(),
        ]);

        $this->assertEquals('extension', $did->destination_type);
        $this->assertNotNull($did->destination_id);
    }
}
