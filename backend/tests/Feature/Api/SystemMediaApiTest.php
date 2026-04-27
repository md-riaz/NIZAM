<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemMediaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_media_for_uuid_scoped_organization(): void
    {
        Storage::fake('public');

        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);

        $organization
            ->addMedia(UploadedFile::fake()->createWithContent('welcome.wav', "RIFF\x24\x00\x00\x00WAVEfmt "))
            ->usingName('Welcome Greeting')
            ->toMediaCollection('prompts');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/organizations/{$organization->id}/system-media");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Welcome Greeting')
            ->assertJsonPath('data.0.collection_name', 'prompts');
    }
}
