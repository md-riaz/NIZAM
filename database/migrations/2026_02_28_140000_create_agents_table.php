<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('extension_id')->constrained('extensions')->cascadeOnDelete();
            $table->string('name');
            $table->string('role')->default('agent'); // agent, supervisor
            $table->string('state')->default('offline'); // available, busy, ringing, paused, offline
            $table->string('pause_reason')->nullable();
            $table->timestamp('state_changed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'extension_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
