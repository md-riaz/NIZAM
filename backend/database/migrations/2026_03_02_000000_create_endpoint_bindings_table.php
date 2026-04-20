<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('endpoint_bindings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('extension_id')->nullable()->constrained('extensions')->nullOnDelete();
            $table->foreignUuid('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->string('type');
            $table->string('device_uuid')->nullable();
            $table->text('push_token')->nullable();
            $table->text('voip_push_token')->nullable();
            $table->string('platform')->nullable();
            $table->string('app_version')->nullable();
            $table->boolean('is_push_capable')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('rings_immediately_when_online')->default(true);
            $table->boolean('allow_late_join_after_push')->default(false);
            $table->string('forward_number')->nullable();
            $table->boolean('forward_requires_confirm')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_registered_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'is_enabled']);
            $table->index(['extension_id', 'is_enabled']);
            $table->index(['agent_id', 'is_enabled']);
            $table->unique(['organization_id', 'device_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('endpoint_bindings');
    }
};
