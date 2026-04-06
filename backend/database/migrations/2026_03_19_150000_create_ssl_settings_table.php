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
        Schema::create('ssl_settings', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('email');
            $blueprint->boolean('is_enabled')->default(false);
            $blueprint->json('domains'); // Array of domains
            $blueprint->enum('status', ['pending', 'active', 'failed', 'expired'])->default('pending');
            $blueprint->text('last_error')->nullable();
            $blueprint->timestamp('last_renewed_at')->nullable();
            $blueprint->timestamp('expires_at')->nullable();
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ssl_settings');
    }
};
