<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ivrs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('greet_long')->nullable();
            $table->string('greet_short')->nullable();
            $table->integer('timeout')->default(5);
            $table->integer('max_failures')->default(3);
            $table->json('options');
            $table->string('timeout_destination_type')->nullable();
            $table->uuid('timeout_destination_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ivrs');
    }
};
