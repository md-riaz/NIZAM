<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_trace_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('call_session_id')->constrained('call_sessions')->cascadeOnDelete();
            $table->string('call_uuid')->index();
            $table->string('node_id')->nullable();
            $table->string('node_type')->nullable();
            $table->string('action')->index();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->timestamps();

            $table->index(['call_session_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_trace_events');
    }
};
