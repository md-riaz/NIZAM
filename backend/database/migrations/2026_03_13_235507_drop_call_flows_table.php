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
        Schema::table('call_sessions', function (Blueprint $table) {
            $table->dropForeign(['call_flow_id']);
            $table->dropColumn('call_flow_id');
        });

        Schema::dropIfExists('call_flows');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('call_flows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->jsonb('nodes')->default('[]');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('call_sessions', function (Blueprint $table) {
            $table->foreignUuid('call_flow_id')->nullable()->constrained('call_flows')->nullOnDelete();
        });
    }
};
