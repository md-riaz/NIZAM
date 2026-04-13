<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropUnique('tenants_slug_unique');
            });
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('slug')->nullable();
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->unique('slug');
        });
    }
};
