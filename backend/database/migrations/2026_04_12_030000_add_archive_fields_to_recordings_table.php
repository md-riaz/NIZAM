<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recordings', function (Blueprint $table) {
            $table->string('storage_driver')->nullable()->after('abandon_reason');
            $table->string('storage_reference')->nullable()->after('storage_driver');
            $table->timestamp('archived_at')->nullable()->after('storage_reference');
            $table->json('archive_metadata')->nullable()->after('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('recordings', function (Blueprint $table) {
            $table->dropColumn([
                'storage_driver',
                'storage_reference',
                'archived_at',
                'archive_metadata',
            ]);
        });
    }
};
