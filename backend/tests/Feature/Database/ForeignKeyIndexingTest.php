<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ForeignKeyIndexingTest extends TestCase
{
    use RefreshDatabase;

    public function test_foreign_keys_relating_to_primary_keys_are_indexed_by_default(): void
    {
        foreach ($this->tablesWithForeignKeys() as $table) {
            $primaryKeyColumns = collect(Schema::getIndexes($table))
                ->firstWhere('primary', true)['columns'] ?? [];

            $foreignKeysToPrimaryKeys = collect(Schema::getForeignKeys($table))
                ->filter(fn (array $foreignKey): bool => ($foreignKey['foreign_columns'] ?? []) === $primaryKeyColumns);

            $indexes = collect(Schema::getIndexes($table));

            foreach ($foreignKeysToPrimaryKeys as $foreignKey) {
                foreach ($foreignKey['columns'] as $column) {
                    $message = sprintf('Expected an index on %s.%s for its foreign-key relation.', $table, $column);

                    $this->assertTrue(
                        $indexes->contains(fn (array $index): bool => (($index['columns'][0] ?? null) === $column)),
                        $message
                    );
                }
            }
        }
    }

    /**
     * Derive target tables from current schema metadata instead of relying only on a hand-maintained allowlist.
     *
     * @return list<string>
     */
    private function tablesWithForeignKeys(): array
    {
        $candidateTables = [
            'extensions',
            'device_profiles',
            'dids',
            'recordings',
            'users',
            'teams',
            'tenants',
            'agents',
            'queues',
            'queue_members',
            'queue_entries',
            'queue_metrics',
            'analytics_events',
            'alert_policies',
            'alerts',
            'transcription_jobs',
            'webhooks',
            'audit_logs',
            'call_events',
            'call_sessions',
            'holiday_calendars',
            'holidays',
            'schedules',
            'team_members',
            'flows',
            'flow_versions',
            'flow_nodes',
            'flow_edges',
            'call_delivery_attempts',
            'push_notification_logs',
            'device_registration_snapshots',
            'flow_compiled_artifacts',
            'tenant_dialplan_manifests',
            'cdr_enrichments',
            'bridges',
            'wallboard_queue_projections',
            'wallboard_agent_projections',
            'sip_profile_settings',
        ];

        return array_values(array_filter($candidateTables, function (string $table): bool {
            return Schema::hasTable($table) && ! empty(Schema::getForeignKeys($table));
        }));
    }
}
