<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gateways', function (Blueprint $table) {
            $table->string('vendor')->nullable()->after('name');
            $table->json('preferred_codecs')->nullable()->after('outbound_codecs');
            $table->string('dtmf_mode')->default('rfc2833')->after('preferred_codecs');
            $table->string('srtp_mode')->default('none')->after('dtmf_mode');
        });
    }

    public function down(): void
    {
        Schema::table('gateways', function (Blueprint $table) {
            $table->dropColumn(['vendor', 'preferred_codecs', 'dtmf_mode', 'srtp_mode']);
        });
    }
};
