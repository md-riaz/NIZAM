<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flow_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('flow_id')->constrained('flows')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('definition_checksum')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_published')->default(false);
            $table->json('definition_json')->nullable();
            $table->timestamps();

            $table->unique(['flow_id', 'version_number']);
            $table->index(['flow_id', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_versions');
    }
};
