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
        Schema::create('organization_dialplan_manifests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('manifest_type')->default('inbound_routing');
            $table->longText('content');
            $table->string('checksum')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            // An organization can only have one active manifest per type
            $table->unique(['organization_id', 'manifest_type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_dialplan_manifests');
    }
};
