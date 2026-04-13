<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extensions', function (Blueprint $table) {
            $table->boolean('follow_me_enabled')->default(false)->after('voicemail_enabled');
            $table->string('follow_me_destination')->nullable()->after('follow_me_enabled');
            $table->boolean('dnd_enabled')->default(false)->after('follow_me_destination');
        });
    }

    public function down(): void
    {
        Schema::table('extensions', function (Blueprint $table) {
            $table->dropColumn([
                'follow_me_enabled',
                'follow_me_destination',
                'dnd_enabled',
            ]);
        });
    }
};
