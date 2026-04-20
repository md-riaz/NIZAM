<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dids', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'number']);
            $table->unique(['organization_id', 'number', 'gateway_id', 'gateway_registration_id']);
        });
    }

    public function down(): void
    {
        Schema::table('dids', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'number', 'gateway_id', 'gateway_registration_id']);
            $table->unique(['organization_id', 'number']);
        });
    }
};
