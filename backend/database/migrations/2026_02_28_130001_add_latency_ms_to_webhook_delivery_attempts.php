<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_delivery_attempts', function (Blueprint $table) {
            $table->decimal('latency_ms', 10, 2)->nullable()->after('success');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_delivery_attempts', function (Blueprint $table) {
            $table->dropColumn('latency_ms');
        });
    }
};
