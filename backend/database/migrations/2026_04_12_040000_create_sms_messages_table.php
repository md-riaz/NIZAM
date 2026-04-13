<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_domain')->index();
            $table->string('direction');
            $table->string('from_number');
            $table->string('to_number');
            $table->text('body');
            $table->string('status');
            $table->string('provider')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_domain', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};
