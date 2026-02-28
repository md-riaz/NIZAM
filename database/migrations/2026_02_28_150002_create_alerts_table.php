<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('alert_policy_id')->constrained('alert_policies')->cascadeOnDelete();
            $table->string('severity'); // critical, warning, info
            $table->string('metric');
            $table->decimal('current_value', 10, 2);
            $table->decimal('threshold_value', 10, 2);
            $table->string('status')->default('open'); // open, acknowledged, resolved
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
