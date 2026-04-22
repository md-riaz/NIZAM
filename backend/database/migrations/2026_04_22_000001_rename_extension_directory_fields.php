<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extensions', function (Blueprint $table) {
            $table->renameColumn('directory_first_name', 'first_name');
            $table->renameColumn('directory_last_name', 'last_name');
        });
    }

    public function down(): void
    {
        Schema::table('extensions', function (Blueprint $table) {
            $table->renameColumn('first_name', 'directory_first_name');
            $table->renameColumn('last_name', 'directory_last_name');
        });
    }
};
