<?php

namespace Tests\Unit\Models;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_created_with_factory(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
        ]);
    }

    public function test_belongs_to_a_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertInstanceOf(Tenant::class, $user->tenant);
        $this->assertEquals($tenant->id, $user->tenant->id);
    }

    public function test_tenant_is_nullable(): void
    {
        $user = User::factory()->create(['tenant_id' => null]);

        $this->assertNull($user->tenant);
    }

    public function test_password_is_hidden(): void
    {
        $user = User::factory()->create();

        $this->assertArrayNotHasKey('password', $user->toArray());
    }

    public function test_remember_token_is_hidden(): void
    {
        $user = User::factory()->create();

        $this->assertArrayNotHasKey('remember_token', $user->toArray());
    }

    public function test_has_correct_fillable_attributes(): void
    {
        $user = new User;
        $expected = ['name', 'email', 'password', 'tenant_id', 'role'];

        $this->assertEquals($expected, $user->getFillable());
    }

    public function test_password_is_hashed(): void
    {
        $user = User::factory()->create([
            'password' => 'plaintext-password',
        ]);

        $this->assertNotEquals('plaintext-password', $user->getAttributes()['password']);
    }
}
