<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReportingIndexCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_reporting_and_routing_tables_have_required_composite_indexes(): void
    {
        $this->assertTrue(
            $this->hasIndex('call_detail_records', 'cdr_tenant_caller_start_idx', ['tenant_id', 'caller_id_number', 'start_stamp']),
            'Expected call_detail_records to have tenant/caller/start composite index.'
        );

        $this->assertTrue(
            $this->hasIndex('recordings', 'recordings_tenant_caller_created_idx', ['tenant_id', 'caller_id_number', 'created_at']),
            'Expected recordings to have tenant/caller/created composite index.'
        );

        // Existing high-value composites that should remain present.
        $this->assertTrue(
            $this->hasIndex('call_events', 'call_events_tenant_type_occurred_idx', ['tenant_id', 'event_type', 'occurred_at']),
            'Expected call_events to have tenant/event_type/occurred_at composite index.'
        );
    }

    private function hasIndex(string $table, string $indexName, array $columns): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        return collect(Schema::getIndexes($table))->contains(function (array $index) use ($indexName, $columns): bool {
            return ($index['name'] ?? null) === $indexName
                && ($index['columns'] ?? []) === $columns;
        });
    }
}
