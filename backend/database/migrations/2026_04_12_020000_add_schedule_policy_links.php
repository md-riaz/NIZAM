<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->foreignUuid('default_schedule_id')
                ->nullable()
                ->after('domain')
                ->constrained('schedules')
                ->nullOnDelete();

            $table->foreignUuid('default_holiday_calendar_id')
                ->nullable()
                ->after('default_schedule_id')
                ->constrained('holiday_calendars')
                ->nullOnDelete();
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->foreignUuid('schedule_id')
                ->nullable()
                ->after('organization_id')
                ->constrained('schedules')
                ->nullOnDelete();

            $table->foreignUuid('holiday_calendar_id')
                ->nullable()
                ->after('schedule_id')
                ->constrained('holiday_calendars')
                ->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('schedule_id')
                ->nullable()
                ->after('organization_id')
                ->constrained('schedules')
                ->nullOnDelete();

            $table->foreignUuid('holiday_calendar_id')
                ->nullable()
                ->after('schedule_id')
                ->constrained('holiday_calendars')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['schedule_id']);
            $table->dropForeign(['holiday_calendar_id']);
            $table->dropColumn(['schedule_id', 'holiday_calendar_id']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['schedule_id']);
            $table->dropForeign(['holiday_calendar_id']);
            $table->dropColumn(['schedule_id', 'holiday_calendar_id']);
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropForeign(['default_schedule_id']);
            $table->dropForeign(['default_holiday_calendar_id']);
            $table->dropColumn(['default_schedule_id', 'default_holiday_calendar_id']);
        });
    }
};
