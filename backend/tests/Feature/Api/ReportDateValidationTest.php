<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A malformed date on a report endpoint is invalid input, not a crash.
 *
 * All three report controllers handed request input straight to `Carbon::parse`,
 * which throws `InvalidFormatException` on a malformed string and `TypeError` on
 * an array. Both surfaced as a 500, so `?date_to=nonsense` — or the array any
 * query string can produce with `?date_to[]=x` — took the endpoint down instead
 * of being reported back to the caller.
 */
class ReportDateValidationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role' => 'admin',
        ]);

        foreach (['cdrs.view', 'cdrs.analytics', 'recordings.view', 'organizations.view'] as $slug) {
            Permission::updateOrCreate(['slug' => $slug], ['module' => 'core']);
        }
        $this->user->grantPermissions(['cdrs.view', 'cdrs.analytics', 'recordings.view', 'organizations.view']);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function reportEndpoints(): array
    {
        return [
            'analytics summary' => ['cdrs/analytics/summary', 'date_from', 'date_to'],
            'analytics volume' => ['cdrs/analytics/volume', 'date_from', 'date_to'],
            'analytics quality' => ['cdrs/analytics/quality', 'date_from', 'date_to'],
            'analytics destinations' => ['cdrs/analytics/destinations', 'date_from', 'date_to'],
            'supervisor call summary' => ['supervisor-reports/call-summary', 'date_from', 'date_to'],
            'supervisor missed calls' => ['supervisor-reports/missed-returned-calls', 'date_from', 'date_to'],
            'supervisor voicemails' => ['supervisor-reports/voicemails-needing-follow-up', 'date_from', 'date_to'],
            // Usage reads from/to rather than date_from/date_to.
            'usage summary' => ['usage/summary', 'from', 'to'],
            'usage reconcile' => ['usage/reconcile', 'from', 'to'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('reportEndpoints')]
    public function test_a_malformed_date_is_rejected_rather_than_crashing(string $path, string $fromKey, string $toKey): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->url($path)."?{$toKey}=nonsense")
            ->assertStatus(422)
            ->assertJsonValidationErrors([$toKey]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('reportEndpoints')]
    public function test_an_array_date_is_rejected_rather_than_crashing(string $path, string $fromKey, string $toKey): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->url($path)."?{$toKey}[]=2026-01-01")
            ->assertStatus(422)
            ->assertJsonValidationErrors([$toKey]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('reportEndpoints')]
    public function test_a_well_formed_range_is_accepted(string $path, string $fromKey, string $toKey): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->url($path)."?{$fromKey}=2026-01-01&{$toKey}=2026-01-31")
            ->assertOk();
    }

    /**
     * A range given backwards is what the caller meant, reversed. Passing it
     * through unchanged returns an empty report with nothing to explain why.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('reportEndpoints')]
    public function test_a_reversed_range_is_swapped_rather_than_returning_nothing(string $path, string $fromKey, string $toKey): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->url($path)."?{$fromKey}=2026-01-31&{$toKey}=2026-01-01")
            ->assertOk();
    }

    public function test_an_unsupported_granularity_is_rejected(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->url('cdrs/analytics/volume').'?granularity=weekly')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['granularity']);
    }

    public function test_an_out_of_range_destination_limit_is_rejected(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->url('cdrs/analytics/destinations').'?limit=0')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->url('cdrs/analytics/destinations').'?limit=5000')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);
    }

    public function test_an_out_of_range_window_is_rejected(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->url('supervisor-reports/missed-returned-calls').'?window_days=0')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['window_days']);
    }

    private function url(string $path): string
    {
        return "/api/v1/organizations/{$this->organization->id}/{$path}";
    }
}
