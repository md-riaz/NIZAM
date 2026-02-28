<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('call_uuid')->index();
            $table->string('event_type'); // started, answered, bridge, hangup, registered, etc.
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['tenant_id', 'occurred_at']);
            $table->index(['call_uuid', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_events');
    }
};
