<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_sessions', function (Blueprint $table) {
            $table->index(['organization_id', 'created_at'], 'call_sessions_organization_created_idx');
            $table->index(['organization_id', 'started_at'], 'call_sessions_organization_started_idx');
            $table->index(['organization_id', 'ended_at'], 'call_sessions_organization_ended_idx');
        });

        Schema::table('queue_entries', function (Blueprint $table) {
            $table->index(['queue_id', 'join_time'], 'queue_entries_queue_join_idx');
            $table->index(['queue_id', 'status', 'join_time'], 'queue_entries_queue_status_join_idx');
            $table->index(['queue_id', 'status', 'answer_time'], 'queue_entries_queue_status_answer_idx');
            $table->index(['queue_id', 'agent_id', 'answer_time'], 'queue_entries_queue_agent_answer_idx');
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->index(['organization_id', 'is_active', 'state'], 'agents_organization_active_state_idx');
        });

        Schema::table('call_events', function (Blueprint $table) {
            $table->index(['organization_id', 'call_uuid', 'occurred_at'], 'call_events_organization_uuid_occurred_idx');
            $table->index(['organization_id', 'event_type', 'occurred_at'], 'call_events_organization_type_occurred_idx');
        });

        Schema::table('call_detail_records', function (Blueprint $table) {
            $table->index(['organization_id', 'direction', 'start_stamp'], 'cdr_organization_direction_start_idx');
            $table->index(['organization_id', 'call_type', 'start_stamp'], 'cdr_organization_type_start_idx');
            $table->index(['organization_id', 'hangup_cause', 'start_stamp'], 'cdr_organization_hangup_start_idx');
        });
    }

    public function down(): void
    {
        Schema::table('call_detail_records', function (Blueprint $table) {
            $table->dropIndex('cdr_organization_hangup_start_idx');
            $table->dropIndex('cdr_organization_type_start_idx');
            $table->dropIndex('cdr_organization_direction_start_idx');
        });

        Schema::table('call_events', function (Blueprint $table) {
            $table->dropIndex('call_events_organization_type_occurred_idx');
            $table->dropIndex('call_events_organization_uuid_occurred_idx');
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->dropIndex('agents_organization_active_state_idx');
        });

        Schema::table('queue_entries', function (Blueprint $table) {
            $table->dropIndex('queue_entries_queue_agent_answer_idx');
            $table->dropIndex('queue_entries_queue_status_answer_idx');
            $table->dropIndex('queue_entries_queue_status_join_idx');
            $table->dropIndex('queue_entries_queue_join_idx');
        });

        Schema::table('call_sessions', function (Blueprint $table) {
            $table->dropIndex('call_sessions_organization_ended_idx');
            $table->dropIndex('call_sessions_organization_started_idx');
            $table->dropIndex('call_sessions_organization_created_idx');
        });
    }
};
