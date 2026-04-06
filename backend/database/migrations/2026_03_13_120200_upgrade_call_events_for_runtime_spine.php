<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_events', function (Blueprint $table) {
            $table->foreignUuid('call_session_id')->nullable()->after('id')->constrained('call_sessions')->nullOnDelete();
            $table->string('event_id')->nullable()->after('call_uuid');
            $table->string('source')->default('freeswitch')->after('event_type');
            $table->timestamp('received_at')->nullable()->after('occurred_at');

            $table->index(['call_session_id', 'occurred_at']);
            $table->unique(['call_uuid', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::table('call_events', function (Blueprint $table) {
            $table->dropUnique(['call_uuid', 'event_id']);
            $table->dropIndex(['call_session_id', 'occurred_at']);
            $table->dropConstrainedForeignId('call_session_id');
            $table->dropColumn(['event_id', 'source', 'received_at']);
        });
    }
};
