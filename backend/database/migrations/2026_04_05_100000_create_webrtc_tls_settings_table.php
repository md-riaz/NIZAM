<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('webrtc_tls_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('webrtc_enabled')->default(false);
            $table->string('active_mode')->default('trusted_ca');
            $table->boolean('trusted_ca_enabled')->default(true);
            $table->string('trusted_ca_cert_dir')->nullable();
            $table->boolean('self_signed_enabled')->default(true);
            $table->string('self_signed_cert_dir')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webrtc_tls_settings');
    }
};
