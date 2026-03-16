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
        Schema::create('tenant_dialplan_manifests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('manifest_type')->default('inbound_routing');
            $table->longText('content');
            $table->string('checksum')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            // A tenant can only have one active manifest per type
            $table->unique(['tenant_id', 'manifest_type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_dialplan_manifests');
    }
};
