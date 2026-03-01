<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedInteger('recording_retention_days')->nullable()->after('max_ring_groups');
            $table->unsignedInteger('max_calls_per_minute')->nullable()->after('recording_retention_days');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['recording_retention_days', 'max_calls_per_minute']);
        });
    }
};
