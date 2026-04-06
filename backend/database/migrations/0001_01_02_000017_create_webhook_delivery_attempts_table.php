<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_delivery_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('webhook_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->json('payload');
            $table->integer('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->integer('attempt');
            $table->boolean('success');
            $table->text('error_message')->nullable();
            $table->timestamp('delivered_at');
            $table->timestamps();

            $table->index(['webhook_id', 'created_at']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_delivery_attempts');
    }
};
