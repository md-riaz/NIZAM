<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('call_uuid')->index();
            $table->integer('version')->default(1);

            // Features
            $table->decimal('wait_time', 10, 2)->nullable();
            $table->decimal('talk_time', 10, 2)->nullable();
            $table->boolean('abandon')->default(false);
            $table->string('agent_id')->nullable();
            $table->string('queue_id')->nullable();
            $table->string('hangup_cause')->nullable();
            $table->integer('retries')->default(0);
            $table->integer('webhook_failures')->default(0);

            // Scoring
            $table->decimal('health_score', 5, 2)->nullable();
            $table->json('score_breakdown')->nullable();

            $table->timestamps();
            $table->unique(['call_uuid', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
