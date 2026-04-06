<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flow_edges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('flow_version_id')->constrained('flow_versions')->cascadeOnDelete();
            $table->foreignUuid('source_node_id')->constrained('flow_nodes')->cascadeOnDelete();
            $table->foreignUuid('target_node_id')->constrained('flow_nodes')->cascadeOnDelete();
            $table->string('condition')->default('default');
            $table->timestamps();

            $table->index(['flow_version_id', 'condition']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_edges');
    }
};
