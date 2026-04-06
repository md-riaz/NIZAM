<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dids', function (Blueprint $table) {
            $table->foreignUuid('gateway_id')->nullable()->after('tenant_id')->constrained('gateways')->nullOnDelete();
            $table->foreignUuid('gateway_registration_id')->nullable()->after('gateway_id')->constrained('gateway_registrations')->nullOnDelete();
            $table->string('normalized_number')->nullable()->after('number')->index();

            $table->index(['tenant_id', 'normalized_number']);
        });
    }

    public function down(): void
    {
        Schema::table('dids', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'normalized_number']);
            $table->dropColumn('normalized_number');
            $table->dropConstrainedForeignId('gateway_registration_id');
            $table->dropConstrainedForeignId('gateway_id');
        });
    }
};
