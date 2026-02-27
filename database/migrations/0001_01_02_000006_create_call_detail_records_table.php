<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_detail_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('uuid')->unique();
            $table->string('caller_id_name')->nullable();
            $table->string('caller_id_number');
            $table->string('destination_number');
            $table->string('context')->nullable();
            $table->timestamp('start_stamp');
            $table->timestamp('answer_stamp')->nullable();
            $table->timestamp('end_stamp')->nullable();
            $table->integer('duration')->default(0);
            $table->integer('billsec')->default(0);
            $table->string('hangup_cause')->nullable();
            $table->string('direction');
            $table->string('recording_path')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'start_stamp']);
            $table->index(['caller_id_number']);
            $table->index(['destination_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_detail_records');
    }
};
