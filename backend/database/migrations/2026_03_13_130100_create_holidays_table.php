<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('holiday_calendar_id')->constrained('holiday_calendars')->cascadeOnDelete();
            $table->string('name');
            $table->date('holiday_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['holiday_calendar_id', 'holiday_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
