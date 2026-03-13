<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_registrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('gateway_id')->constrained('gateways')->cascadeOnDelete();
            $table->string('registration_identifier')->unique();
            $table->string('username')->nullable();
            $table->string('realm')->nullable();
            $table->string('proxy')->nullable();
            $table->string('transport')->nullable();
            $table->string('status')->default('unknown')->index();
            $table->timestamp('last_registered_at')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['gateway_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_registrations');
    }
};
