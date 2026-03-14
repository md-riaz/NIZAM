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
        Schema::table('flow_versions', function (Blueprint $table) {
            $table->string('runtime_mode')->default('interpreted')->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('flow_versions', function (Blueprint $table) {
            $table->dropColumn('runtime_mode');
        });
    }
};
