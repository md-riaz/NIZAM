<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bridges', function (Blueprint $table) {
            $table->string('codec_policy')->default('default')->after('destination_template');
            $table->json('codec_list')->nullable()->after('codec_policy');
            $table->string('transcode_policy')->default('none')->after('codec_list');
        });
    }

    public function down(): void
    {
        Schema::table('bridges', function (Blueprint $table) {
            $table->dropColumn(['codec_policy', 'codec_list', 'transcode_policy']);
        });
    }
};
