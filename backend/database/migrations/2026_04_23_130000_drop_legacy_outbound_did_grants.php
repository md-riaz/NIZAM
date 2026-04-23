<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('phone_number_user_access');
        Schema::dropIfExists('phone_number_team_access');
        Schema::dropIfExists('phone_number_device_access');

        if (Schema::hasColumn('users', 'default_outbound_did_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('default_outbound_did_id');
            });
        }

        if (Schema::hasColumn('device_profiles', 'default_outbound_did_id')) {
            Schema::table('device_profiles', function (Blueprint $table) {
                $table->dropConstrainedForeignId('default_outbound_did_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'default_outbound_did_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignUuid('default_outbound_did_id')->nullable()->constrained('dids')->nullOnDelete()->after('holiday_calendar_id');
            });
        }

        if (! Schema::hasColumn('device_profiles', 'default_outbound_did_id')) {
            Schema::table('device_profiles', function (Blueprint $table) {
                $table->foreignUuid('default_outbound_did_id')->nullable()->constrained('dids')->nullOnDelete()->after('extension_id');
            });
        }

        if (! Schema::hasTable('phone_number_user_access')) {
            Schema::create('phone_number_user_access', function (Blueprint $table) {
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignUuid('did_id')->constrained('dids')->cascadeOnDelete();
                $table->timestamps();
                $table->primary(['user_id', 'did_id']);
            });
        }

        if (! Schema::hasTable('phone_number_team_access')) {
            Schema::create('phone_number_team_access', function (Blueprint $table) {
                $table->foreignUuid('team_id')->constrained()->cascadeOnDelete();
                $table->foreignUuid('did_id')->constrained('dids')->cascadeOnDelete();
                $table->timestamps();
                $table->primary(['team_id', 'did_id']);
            });
        }

        if (! Schema::hasTable('phone_number_device_access')) {
            Schema::create('phone_number_device_access', function (Blueprint $table) {
                $table->foreignUuid('device_profile_id')->constrained()->cascadeOnDelete();
                $table->foreignUuid('did_id')->constrained('dids')->cascadeOnDelete();
                $table->timestamps();
                $table->primary(['device_profile_id', 'did_id']);
            });
        }
    }
};
