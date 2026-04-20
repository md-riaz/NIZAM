<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables and foreign-key columns that still need standalone indexes.
     *
     * The project rule is that every foreign key referencing a primary key
     * should be indexed by default, unless an existing index already begins
     * with the foreign-key column.
     *
     * @var array<string, list<string>>
     */
    private array $requiredIndexes = [
        'extensions' => ['user_id'],
        'device_profiles' => ['extension_id', 'user_id'],
        'dids' => ['gateway_id'],
        'users' => ['organization_id', 'schedule_id', 'holiday_calendar_id'],
        'teams' => ['schedule_id', 'holiday_calendar_id'],
        'organizations' => ['default_schedule_id', 'default_holiday_calendar_id'],
        'agents' => ['extension_id'],
        'queues' => ['organization_id'],
        'queue_members' => ['agent_id'],
        'queue_metrics' => ['organization_id', 'queue_id'],
        'queue_entries' => ['agent_id'],
        'analytics_events' => ['organization_id'],
        'alert_policies' => ['organization_id'],
        'alerts' => ['alert_policy_id'],
        'transcription_jobs' => ['recording_id'],
        'webhooks' => ['organization_id'],
        'call_sessions' => ['did_id', 'call_flow_id'],
        'holidays' => ['holiday_calendar_id'],
        'schedules' => ['holiday_calendar_id'],
        'flows' => ['active_version_id'],
        'flow_edges' => ['source_node_id', 'target_node_id'],
        'flow_compiled_artifacts' => ['organization_id'],
        'cdr_enrichments' => ['cdr_id'],
        'bridges' => ['gateway_id'],
        'wallboard_queue_projections' => ['queue_id'],
        'wallboard_agent_projections' => ['agent_id'],
    ];

    public function up(): void
    {
        foreach ($this->requiredIndexes as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $existingIndexes = collect(Schema::getIndexes($table));

            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns, $existingIndexes): void {
                foreach ($columns as $column) {
                    if (! Schema::hasColumn($table, $column)) {
                        continue;
                    }

                    $hasCoveringIndex = $existingIndexes->contains(function (array $index) use ($column): bool {
                        $indexColumns = $index['columns'] ?? [];

                        return ($indexColumns[0] ?? null) === $column;
                    });

                    if ($hasCoveringIndex) {
                        continue;
                    }

                    $blueprint->index($column, $this->indexName($table, $column));
                }
            });
        }
    }

    public function down(): void
    {
        // Intentionally a no-op.
        //
        // This migration adds safety/performance indexes only when missing, but it does
        // not persist which indexes were created by this migration versus which existed
        // before. Dropping by conventional name on rollback could remove a pre-existing
        // index and make the migration unsafe to reverse. Leaving rollback as a no-op is
        // safer than risking destructive index removal.
    }

    private function indexName(string $table, string $column): string
    {
        return strtolower(sprintf('%s_%s_index', $table, $column));
    }
};
