<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('queue_id')->constrained('queues')->cascadeOnDelete();
            $table->string('call_uuid');
            $table->string('caller_id_number')->nullable();
            $table->string('caller_id_name')->nullable();
            $table->string('status')->default('waiting'); // waiting, ringing, answered, abandoned, overflowed
            $table->foreignUuid('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->timestamp('join_time');
            $table->timestamp('answer_time')->nullable();
            $table->timestamp('abandon_time')->nullable();
            $table->integer('wait_duration')->nullable(); // seconds
            $table->string('abandon_reason')->nullable();
            $table->timestamps();
            $table->index(['queue_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_entries');
    }
};
