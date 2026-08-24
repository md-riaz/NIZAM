<?php

namespace Tests\Feature\Api;

use App\Models\CallDetailRecord;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use Carbon\Carbon;
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
     * A reversed range must not error on any endpoint.
     *
     * This only establishes that nothing blows up. The cases below prove the
     * range was actually reordered — on its own, a 200 carrying an empty report
     * would satisfy this and hide a regression.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('reportEndpoints')]
    public function test_a_reversed_range_does_not_error(string $path, string $fromKey, string $toKey): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->url($path)."?{$fromKey}=2026-01-31&{$toKey}=2026-01-01")
            ->assertOk();
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function rangeEchoingEndpoints(): array
    {
        return [
            // path, fromKey, toKey, dot-path to the echoed lower bound
            'analytics summary' => ['cdrs/analytics/summary', 'date_from', 'date_to', 'data.period'],
            'supervisor call summary' => ['supervisor-reports/call-summary', 'date_from', 'date_to', 'data.period'],
            'usage summary' => ['usage/summary', 'from', 'to', 'data'],
        ];
    }

    /**
     * The endpoints that echo their range must report it the right way round.
     *
     * This is the assertion that actually pins the swap: the response has to
     * come back with January 1st as the lower bound, not the 31st it was sent.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('rangeEchoingEndpoints')]
    public function test_a_reversed_range_is_echoed_back_in_order(string $path, string $fromKey, string $toKey, string $echoPath): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson($this->url($path)."?{$fromKey}=2026-01-31&{$toKey}=2026-01-01")
            ->assertOk();

        $echoed = $response->json($echoPath);

        $this->assertStringStartsWith('2026-01-01', (string) $echoed['from'], 'The lower bound was not reordered.');
        $this->assertStringStartsWith('2026-01-31', (string) $echoed['to'], 'The upper bound was not reordered.');
    }

    /**
     * A reversed range must still count a call inside it.
     *
     * Reordering is only worth anything if the widened range actually selects
     * rows; an empty report would satisfy the status-only case above.
     */
    public function test_a_reversed_range_still_counts_calls_within_it(): void
    {
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'start_stamp' => '2026-01-15 10:00:00',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->url('cdrs/analytics/summary').'?date_from=2026-01-31&date_to=2026-01-01')
            ->assertOk()
            ->assertJsonPath('data.total_calls', 1);
    }

    /**
     * A bound supplied on its own must still bound the answer.
     *
     * Reordering compared an explicit bound against the default generated
     * opposite it, so a lone `date_to` before the default window was swapped with
     * it: `?date_to=2020-01-01` reported the range from a month ago *up to* 2020
     * instead of the range ending at the bound that was asked for.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('rangeEchoingEndpoints')]
    public function test_a_lone_upper_bound_stays_the_upper_bound(string $path, string $fromKey, string $toKey, string $echoPath): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson($this->url($path)."?{$toKey}=2020-01-01")
            ->assertOk();

        $echoed = $response->json($echoPath);

        $this->assertStringStartsWith('2020-01-01', (string) $echoed['to'], 'The requested upper bound was not honoured.');
        $this->assertLessThanOrEqual(
            (string) $echoed['to'],
            (string) $echoed['from'],
            'The generated lower bound was placed after the requested upper bound.'
        );
    }

    /**
     * The same defect, seen through the data rather than the echoed range.
     *
     * The swapped range spanned everything between 2020 and a month ago, so a
     * call in 2021 was counted for a request that asked for nothing after 2020.
     */
    public function test_a_lone_upper_bound_excludes_calls_after_it(): void
    {
        CallDetailRecord::factory()->create([
            'organization_id' => $this->organization->id,
            'start_stamp' => '2021-06-01 10:00:00',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->url('cdrs/analytics/summary').'?date_to=2020-01-01')
            ->assertOk()
            ->assertJsonPath('data.total_calls', 0);
    }

    /**
     * A lone lower bound in the future was inverted the same way, which for the
     * usage endpoints means metering a range the caller never asked about.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('rangeEchoingEndpoints')]
    public function test_a_lone_future_lower_bound_stays_the_lower_bound(string $path, string $fromKey, string $toKey, string $echoPath): void
    {
        $future = Carbon::today()->addYears(5)->toDateString();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson($this->url($path)."?{$fromKey}={$future}")
            ->assertOk();

        $echoed = $response->json($echoPath);

        $this->assertStringStartsWith($future, (string) $echoed['from'], 'The requested lower bound was not honoured.');
        $this->assertGreaterThanOrEqual(
            (string) $echoed['from'],
            (string) $echoed['to'],
            'The generated upper bound was placed before the requested lower bound.'
        );
    }

    /**
     * With neither bound supplied the defaults must be left alone.
     */
    public function test_an_absent_range_keeps_the_default_window(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson($this->url('cdrs/analytics/summary'))
            ->assertOk();

        $period = $response->json('data.period');

        $this->assertStringStartsWith(Carbon::today()->subDays(30)->toDateString(), (string) $period['from']);
        $this->assertStringStartsWith(Carbon::today()->toDateString(), (string) $period['to']);
    }

    /**
     * A blank bound is an absent bound.
     *
     * An empty form field submits `?date_to=`, which passes `nullable|date`.
     * `Carbon::parse('')` returns the current time rather than failing, so the
     * blank field read as an explicit "now" — and on the usage endpoints, as an
     * explicit bound that suppressed the month-to-date default.
     */
    public function test_a_blank_bound_falls_back_to_the_default(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson($this->url('cdrs/analytics/summary').'?date_from=&date_to=')
            ->assertOk();

        $period = $response->json('data.period');

        $this->assertStringStartsWith(Carbon::today()->subDays(30)->toDateString(), (string) $period['from']);
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
