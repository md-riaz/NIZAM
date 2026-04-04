<?php

use App\Models\CallDeliveryAttempt;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_delivery_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('call_session_id')->constrained('call_sessions')->cascadeOnDelete();
            $table->foreignUuid('endpoint_binding_id')->constrained('endpoint_bindings')->cascadeOnDelete();
            $table->string('attempt_type');
            $table->string('status')->default(CallDeliveryAttempt::STATUS_PLANNED);
            $table->uuid('freeswitch_leg_uuid')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('call_session_id');
            $table->index('endpoint_binding_id');
            $table->index('freeswitch_leg_uuid');
            $table->index(['call_session_id', 'status']);
            $table->index(['call_session_id', 'attempt_type']);
            $table->index(['call_session_id', 'endpoint_binding_id']);
            $table->index(['call_session_id', 'status', 'started_at'], 'cda_session_status_started_idx');
        });

        DB::statement(sprintf(
            "CREATE UNIQUE INDEX call_delivery_attempts_winner_unique ON call_delivery_attempts (call_session_id) WHERE status = '%s'",
            CallDeliveryAttempt::STATUS_WON
        ));
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS call_delivery_attempts_winner_unique');
        Schema::dropIfExists('call_delivery_attempts');
    }
};
