<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extensions', function (Blueprint $table) {
            $table->foreignUuid('default_outbound_did_id')
                ->nullable()
                ->after('device_profile_id')
                ->constrained('dids')
                ->nullOnDelete();

            $table->foreignUuid('default_outbound_gateway_id')
                ->nullable()
                ->after('default_outbound_did_id')
                ->constrained('gateways')
                ->nullOnDelete();
        });

        Schema::create('extension_outbound_did', function (Blueprint $table) {
            $table->foreignUuid('extension_id')->constrained('extensions')->cascadeOnDelete();
            $table->foreignUuid('did_id')->constrained('dids')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['extension_id', 'did_id']);
        });

        Schema::create('extension_outbound_gateway', function (Blueprint $table) {
            $table->foreignUuid('extension_id')->constrained('extensions')->cascadeOnDelete();
            $table->foreignUuid('gateway_id')->constrained('gateways')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['extension_id', 'gateway_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extension_outbound_gateway');
        Schema::dropIfExists('extension_outbound_did');

        Schema::table('extensions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_outbound_gateway_id');
            $table->dropConstrainedForeignId('default_outbound_did_id');
        });
    }
};
