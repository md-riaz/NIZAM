<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateways', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('host');
            $table->integer('port')->default(5060);
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->string('realm')->nullable();
            $table->string('transport')->default('udp');
            $table->json('inbound_codecs')->nullable();
            $table->json('outbound_codecs')->nullable();
            $table->boolean('allow_transcoding')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateways');
    }
};
