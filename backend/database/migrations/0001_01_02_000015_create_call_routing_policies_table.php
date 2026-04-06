<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_routing_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->json('conditions');
            $table->string('match_destination_type')->nullable();
            $table->uuid('match_destination_id')->nullable();
            $table->string('no_match_destination_type')->nullable();
            $table->uuid('no_match_destination_id')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_routing_policies');
    }
};
