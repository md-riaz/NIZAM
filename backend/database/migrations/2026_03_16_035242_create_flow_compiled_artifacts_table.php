<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('flow_compiled_artifacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('flow_version_id')->constrained()->cascadeOnDelete();
            $table->string('artifact_type')->default('dialplan_xml');
            $table->longText('content');
            $table->string('checksum')->nullable();
            $table->timestamps();

            $table->unique(['flow_version_id', 'artifact_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flow_compiled_artifacts');
    }
};
