<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('default_outbound_did_id')->nullable()->after('holiday_calendar_id')->constrained('dids')->nullOnDelete();
        });

        Schema::table('device_profiles', function (Blueprint $table) {
            $table->foreignUuid('default_outbound_did_id')->nullable()->after('extension_id')->constrained('dids')->nullOnDelete();
        });

        Schema::create('phone_number_user_access', function (Blueprint $table) {
            $table->uuid('did_id');
            $table->foreignId('user_id');
            $table->timestamps();

            $table->primary(['did_id', 'user_id']);
            $table->foreign('did_id')->references('id')->on('dids')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('phone_number_team_access', function (Blueprint $table) {
            $table->uuid('did_id');
            $table->uuid('team_id');
            $table->timestamps();

            $table->primary(['did_id', 'team_id']);
            $table->foreign('did_id')->references('id')->on('dids')->cascadeOnDelete();
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
        });

        Schema::create('phone_number_device_access', function (Blueprint $table) {
            $table->uuid('did_id');
            $table->uuid('device_profile_id');
            $table->timestamps();

            $table->primary(['did_id', 'device_profile_id']);
            $table->foreign('did_id')->references('id')->on('dids')->cascadeOnDelete();
            $table->foreign('device_profile_id')->references('id')->on('device_profiles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_number_device_access');
        Schema::dropIfExists('phone_number_team_access');
        Schema::dropIfExists('phone_number_user_access');

        Schema::table('device_profiles', function (Blueprint $table) {
            $table->dropForeign(['default_outbound_did_id']);
            $table->dropColumn('default_outbound_did_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['default_outbound_did_id']);
            $table->dropColumn('default_outbound_did_id');
        });
    }
};
