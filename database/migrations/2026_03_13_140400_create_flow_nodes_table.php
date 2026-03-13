<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flow_nodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('flow_version_id')->constrained('flow_versions')->cascadeOnDelete();
            $table->string('type');
            $table->string('name');
            $table->json('config_json')->nullable();
            $table->integer('position_x')->nullable();
            $table->integer('position_y')->nullable();
            $table->timestamps();

            $table->index(['flow_version_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_nodes');
    }
};
