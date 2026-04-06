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
        Schema::create('cdr_enrichments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cdr_id')->constrained('call_detail_records')->cascadeOnDelete();
            $table->string('destination_country')->nullable();
            $table->string('destination_city')->nullable();
            $table->string('carrier_name')->nullable();
            $table->enum('number_type', ['mobile', 'landline', 'voip', 'toll_free', 'unknown'])->default('unknown');
            $table->json('geolocation')->nullable()->comment('lat/lng if available');
            $table->timestamp('enriched_at')->nullable();
            $table->timestamps();

            $table->index('destination_country');
            $table->index('carrier_name');
            $table->index('number_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cdr_enrichments');
    }
};
