<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('recording_policy')->default('off')->after('status');
        });

        Schema::table('dids', function (Blueprint $table) {
            $table->string('recording_policy')->default('inherit')->after('description');
        });

        Schema::table('extensions', function (Blueprint $table) {
            $table->string('recording_policy')->default('inherit')->after('effective_caller_id_name');
        });
    }

    public function down(): void
    {
        Schema::table('extensions', function (Blueprint $table) {
            $table->dropColumn('recording_policy');
        });

        Schema::table('dids', function (Blueprint $table) {
            $table->dropColumn('recording_policy');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('recording_policy');
        });
    }
};
