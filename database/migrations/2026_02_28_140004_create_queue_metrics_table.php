<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('queue_id')->constrained('queues')->cascadeOnDelete();
            $table->string('period'); // hourly, daily
            $table->timestamp('period_start');
            $table->integer('calls_offered')->default(0);
            $table->integer('calls_answered')->default(0);
            $table->integer('calls_abandoned')->default(0);
            $table->decimal('average_wait_time', 8, 2)->default(0);
            $table->decimal('max_wait_time', 8, 2)->default(0);
            $table->decimal('service_level', 5, 2)->default(0); // percentage
            $table->decimal('abandon_rate', 5, 2)->default(0); // percentage
            $table->decimal('agent_occupancy', 5, 2)->default(0); // percentage
            $table->timestamps();
            $table->unique(['queue_id', 'period', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_metrics');
    }
};
