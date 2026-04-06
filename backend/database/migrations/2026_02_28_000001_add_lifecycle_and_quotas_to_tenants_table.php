<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('status')->default('active')->after('is_active');
            $table->integer('max_concurrent_calls')->default(0)->after('max_extensions');
            $table->integer('max_dids')->default(0)->after('max_concurrent_calls');
            $table->integer('max_ring_groups')->default(0)->after('max_dids');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['status', 'max_concurrent_calls', 'max_dids', 'max_ring_groups']);
        });
    }
};
