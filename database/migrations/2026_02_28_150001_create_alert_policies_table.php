<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('metric'); // abandon_rate, webhook_failures, gateway_flapping, sla_drop
            $table->string('condition'); // gt, lt, gte, lte, eq
            $table->decimal('threshold', 10, 2);
            $table->integer('window_minutes')->default(60);
            $table->json('channels'); // ['email', 'webhook', 'slack']
            $table->json('recipients')->nullable(); // email addresses, webhook URLs, slack channels
            $table->boolean('is_active')->default(true);
            $table->integer('cooldown_minutes')->default(15);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_policies');
    }
};
