<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extensions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('extension');
            $table->string('password');
            $table->string('directory_first_name');
            $table->string('directory_last_name');
            $table->string('effective_caller_id_name')->nullable();
            $table->string('effective_caller_id_number')->nullable();
            $table->string('outbound_caller_id_name')->nullable();
            $table->string('outbound_caller_id_number')->nullable();
            $table->boolean('voicemail_enabled')->default(false);
            $table->string('voicemail_pin')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'extension']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extensions');
    }
};
