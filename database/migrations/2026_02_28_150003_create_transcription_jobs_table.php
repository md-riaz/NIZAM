<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcription_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('recording_id')->constrained('recordings')->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->string('provider')->nullable(); // provider name (future)
            $table->longText('transcript_text')->nullable();
            $table->json('transcript_timing')->nullable(); // word-level timing data
            $table->string('language')->default('en');
            $table->integer('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcription_jobs');
    }
};
