<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processed_cdr_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('checksum', 64)->nullable();
            $table->string('dedupe_key', 64)->unique();
            $table->string('status');
            $table->string('call_uuid')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['file_path', 'checksum']);
            $table->index(['status', 'processed_at']);
            $table->index('call_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_cdr_files');
    }
};
