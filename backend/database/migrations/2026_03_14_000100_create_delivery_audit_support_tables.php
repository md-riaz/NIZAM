<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_notification_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('call_session_id')->constrained('call_sessions')->cascadeOnDelete();
            $table->foreignUuid('endpoint_binding_id')->constrained('endpoint_bindings')->cascadeOnDelete();
            $table->string('push_type');
            $table->string('provider_message_id')->nullable();
            $table->string('status');
            $table->timestamp('sent_at')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamps();

            $table->index(['call_session_id', 'sent_at']);
            $table->index(['endpoint_binding_id', 'sent_at']);
            $table->index(['status', 'sent_at']);
        });

        Schema::create('device_registration_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('endpoint_binding_id')->nullable()->constrained('endpoint_bindings')->nullOnDelete();
            $table->foreignUuid('extension_id')->nullable()->constrained('extensions')->nullOnDelete();
            $table->string('registration_key');
            $table->boolean('registered');
            $table->string('user_agent')->nullable();
            $table->string('network_ip')->nullable();
            $table->timestamp('observed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'registration_key']);
            $table->index(['endpoint_binding_id', 'observed_at']);
            $table->index(['extension_id', 'observed_at']);
            $table->index(['tenant_id', 'observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_registration_snapshots');
        Schema::dropIfExists('push_notification_logs');
    }
};
