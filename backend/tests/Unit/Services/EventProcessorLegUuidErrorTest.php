<?php

namespace Tests\Unit\Services;

use App\Models\CallDeliveryAttempt;
use App\Models\Extension;
use App\Models\Organization;
use App\Services\EventProcessor;
use App\Services\WebhookDispatcher;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PDOException;
use Tests\TestCase;

/**
 * PostgreSQL raises when a value cannot be parsed as the column's type; SQLite,
 * which the suite runs on, simply does not match. So a hangup event carrying a
 * non-uuid leg identifier proves nothing here on its own — it takes the same
 * path as any other miss, and would pass with the error handling removed.
 *
 * These drive the failure in directly instead, so the behaviour under the
 * database that actually raises is pinned on the database that does not.
 */
class EventProcessorLegUuidErrorTest extends TestCase
{
    use RefreshDatabase;

    protected function processorThrowing(string $sqlState): EventProcessor
    {
        return new class($this->createMock(WebhookDispatcher::class), $sqlState) extends EventProcessor
        {
            public bool $lookupAttempted = false;

            public function __construct(WebhookDispatcher $dispatcher, private string $sqlState)
            {
                parent::__construct($dispatcher);
            }

            protected function queryAttemptByLegUuid(string $organizationId, string $legUuid): ?CallDeliveryAttempt
            {
                $this->lookupAttempted = true;

                $previous = new PDOException('SQLSTATE['.$this->sqlState.']: query failed');
                $previous->errorInfo = [$this->sqlState, 7, 'query failed'];

                throw new QueryException(
                    'pgsql',
                    'select * from "call_delivery_attempts"',
                    [],
                    $previous
                );
            }
        };
    }

    protected function hangupEvent(string $uniqueId): array
    {
        return [
            'Event-Name' => 'CHANNEL_HANGUP_COMPLETE',
            'variable_domain_name' => 'test.example.com',
            'Unique-ID' => $uniqueId,
            'Caller-Caller-ID-Number' => '1001',
            'Caller-Destination-Number' => '1002',
            'Call-Direction' => 'inbound',
            'Hangup-Cause' => 'NORMAL_CLEARING',
            'variable_duration' => '30',
            'variable_billsec' => '25',
        ];
    }

    protected function createOrganization(): Organization
    {
        $organization = Organization::factory()->create([
            'domain' => 'test.example.com',
            'is_active' => true,
            'status' => Organization::STATUS_ACTIVE,
        ]);

        Extension::factory()->create([
            'organization_id' => $organization->id,
            'extension' => '1001',
            'is_active' => true,
        ]);

        return $organization;
    }

    public function test_a_value_the_column_cannot_represent_is_treated_as_a_miss(): void
    {
        $organization = $this->createOrganization();

        // 22P02 is PostgreSQL's invalid text representation: the value can
        // never match a row, so the call still has to be recorded and billed.
        $processor = $this->processorThrowing('22P02');

        $processor->process($this->hangupEvent('not-a-uuid-at-all'));

        $this->assertTrue($processor->lookupAttempted);
        $this->assertDatabaseHas('call_detail_records', [
            'uuid' => 'not-a-uuid-at-all',
            'organization_id' => $organization->id,
            'billsec' => 25,
        ]);
    }

    public function test_any_other_database_failure_still_propagates(): void
    {
        $this->createOrganization();

        // 08006 is a lost connection. Swallowing that would turn an outage into
        // silently missing call records, which is the failure this whole change
        // exists to prevent.
        $processor = $this->processorThrowing('08006');

        $this->expectException(QueryException::class);

        $processor->process($this->hangupEvent('11111111-2222-3333-4444-555555555555'));
    }
}
