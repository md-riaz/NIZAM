<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_detail_records', function (Blueprint $table) {
            $table->string('read_codec')->nullable()->after('recording_path');
            $table->string('write_codec')->nullable()->after('read_codec');
            $table->string('negotiated_codec')->nullable()->after('write_codec');
        });
    }

    public function down(): void
    {
        Schema::table('call_detail_records', function (Blueprint $table) {
            $table->dropColumn(['read_codec', 'write_codec', 'negotiated_codec']);
        });
    }
};
