<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extensions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('tenant_id')->constrained('users')->nullOnDelete();
            $table->boolean('is_primary')->default(false)->after('voicemail_pin');
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'is_primary']);
        });

        Schema::table('device_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('tenant_id')->constrained('users')->nullOnDelete();
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('device_profiles', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['tenant_id', 'user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('extensions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['tenant_id', 'user_id']);
            $table->dropIndex(['tenant_id', 'is_primary']);
            $table->dropColumn(['user_id', 'is_primary']);
        });
    }
};
