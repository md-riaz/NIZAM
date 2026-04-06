<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_events', function (Blueprint $table) {
            $table->string('schema_version', 10)->default('1.0')->after('payload');
        });
    }

    public function down(): void
    {
        Schema::table('call_events', function (Blueprint $table) {
            $table->dropColumn('schema_version');
        });
    }
};
