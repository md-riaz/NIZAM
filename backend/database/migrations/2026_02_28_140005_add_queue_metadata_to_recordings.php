<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recordings', function (Blueprint $table) {
            $table->string('queue_name')->nullable()->after('destination_number');
            $table->string('agent_id')->nullable()->after('queue_name');
            $table->integer('wait_time')->nullable()->after('agent_id');
            $table->string('outcome')->nullable()->after('wait_time');
            $table->string('abandon_reason')->nullable()->after('outcome');
        });
    }

    public function down(): void
    {
        Schema::table('recordings', function (Blueprint $table) {
            $table->dropColumn(['queue_name', 'agent_id', 'wait_time', 'outcome', 'abandon_reason']);
        });
    }
};
