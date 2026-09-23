<?php

namespace Tests\Feature\Console;

use App\Models\SipProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CompileSipProfilesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('framework/testing/sip_profiles_'.uniqid());
        config(['telephony.sip_profile_provisioning.directory' => $this->directory]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    protected function makeProfile(string $name, bool $active = true): SipProfile
    {
        return SipProfile::create([
            'name' => $name,
            'description' => $name.' profile',
            'is_active' => $active,
        ]);
    }

    public function test_it_writes_the_profiles_freeswitch_requires(): void
    {
        $this->makeProfile('internal');
        $this->makeProfile('external');

        $this->artisan('nizam:compile-sip-profiles')->assertSuccessful();

        $this->assertFileExists($this->directory.'/internal.xml');
        $this->assertFileExists($this->directory.'/external.xml');
    }

    public function test_it_fails_when_a_required_profile_is_inactive(): void
    {
        // The compiler writes only active profiles and deletes the rest, so
        // turning one off removes its file. The other still compiles, so a
        // count of what was written would report success while leaving
        // FreeSWITCH unable to start.
        $this->makeProfile('internal');
        $this->makeProfile('external', active: false);

        $this->artisan('nizam:compile-sip-profiles')
            ->expectsOutputToContain('external')
            ->assertFailed();

        $this->assertFileExists($this->directory.'/internal.xml');
        $this->assertFileDoesNotExist($this->directory.'/external.xml');
    }

    public function test_it_fails_when_there_are_no_profiles_at_all(): void
    {
        $this->artisan('nizam:compile-sip-profiles')->assertFailed();
    }

    public function test_it_does_not_reactivate_a_profile_an_administrator_turned_off(): void
    {
        $this->makeProfile('internal');
        $external = $this->makeProfile('external', active: false);

        $this->artisan('nizam:compile-sip-profiles')->assertFailed();

        $this->assertFalse((bool) $external->fresh()->is_active);
    }
}
