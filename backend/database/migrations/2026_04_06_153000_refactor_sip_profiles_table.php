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
        // 1. Modify existing sip_profiles table
        Schema::table('sip_profiles', function (Blueprint $table) {
            $table->string('hostname')->nullable()->after('name');
            $table->dropColumn('settings');
        });

        // 2. Create sip_profile_settings table
        Schema::create('sip_profile_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sip_profile_id')->constrained('sip_profiles')->onDelete('cascade');
            $table->string('name');
            $table->string('value');
            $table->string('description')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['sip_profile_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sip_profile_settings');

        Schema::table('sip_profiles', function (Blueprint $table) {
            $table->dropColumn('hostname');
            $table->json('settings')->nullable();
        });
    }
};
