<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallboard_queue_projections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('queue_id')->constrained('queues')->cascadeOnDelete();
            $table->string('queue_name');
            $table->integer('waiting_count')->default(0);
            $table->integer('calls_offered')->default(0);
            $table->integer('calls_answered')->default(0);
            $table->integer('calls_abandoned')->default(0);
            $table->decimal('average_wait_time', 8, 2)->default(0);
            $table->decimal('max_wait_time', 8, 2)->default(0);
            $table->decimal('service_level', 5, 2)->default(100);
            $table->decimal('abandon_rate', 5, 2)->default(0);
            $table->decimal('agent_occupancy', 5, 2)->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'queue_id']);
            $table->index(['tenant_id', 'updated_at']);
        });

        Schema::create('wallboard_agent_projections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->string('name');
            $table->string('role')->nullable();
            $table->string('state');
            $table->string('pause_reason')->nullable();
            $table->timestamp('state_changed_at')->nullable();
            $table->string('extension')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'agent_id']);
            $table->index(['tenant_id', 'is_active', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallboard_agent_projections');
        Schema::dropIfExists('wallboard_queue_projections');
    }
};
