<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extensions', function (Blueprint $table) {
            $table->foreignUuid('device_profile_id')->nullable()->after('user_id')->constrained('device_profiles')->nullOnDelete();
            $table->index(['organization_id', 'device_profile_id']);
            $table->unique(['organization_id', 'device_profile_id']);
            $table->unique(['organization_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('extensions', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'user_id']);
            $table->dropUnique(['organization_id', 'device_profile_id']);
            $table->dropForeign(['device_profile_id']);
            $table->dropIndex(['organization_id', 'device_profile_id']);
            $table->dropColumn('device_profile_id');
        });
    }
};
