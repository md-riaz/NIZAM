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
        Schema::table('extensions', function (Blueprint $table) {
            $table->string('outbound_caller_id_privacy')->nullable()->after('outbound_caller_id_number');
            $table->boolean('outbound_caller_id_pai')->default(false)->after('outbound_caller_id_privacy');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('extensions', function (Blueprint $table) {
            $table->dropColumn(['outbound_caller_id_privacy', 'outbound_caller_id_pai']);
        });
    }
};
