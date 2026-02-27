<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dids', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('number');
            $table->string('description')->nullable();
            $table->string('destination_type');
            $table->uuid('destination_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['destination_type', 'destination_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dids');
    }
};
