<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('call_uuid')->unique();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('did_id')->nullable()->constrained('dids')->nullOnDelete();
            $table->foreignUuid('call_flow_id')->nullable()->constrained('call_flows')->nullOnDelete();
            $table->string('flow_version_id')->nullable();
            $table->string('current_node_id')->nullable();
            $table->string('state')->default('initiated')->index();
            $table->json('variables')->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('ended_at')->nullable()->index();
            $table->timestamps();

            $table->index(['tenant_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_sessions');
    }
};
