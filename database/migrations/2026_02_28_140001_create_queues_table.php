<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('strategy')->default('round_robin'); // ring_all, round_robin, least_recent
            $table->integer('max_wait_time')->default(300); // seconds
            $table->string('overflow_action')->default('voicemail'); // voicemail, hangup, extension
            $table->string('overflow_destination')->nullable();
            $table->string('music_on_hold')->nullable();
            $table->integer('service_level_threshold')->default(20); // seconds for SLA
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queues');
    }
};
