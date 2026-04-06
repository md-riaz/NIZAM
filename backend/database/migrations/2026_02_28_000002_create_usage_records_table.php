<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('metric');
            $table->decimal('value', 16, 4)->default(0);
            $table->json('metadata')->nullable();
            $table->date('recorded_date');
            $table->timestamps();

            $table->index(['tenant_id', 'metric', 'recorded_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_records');
    }
};
