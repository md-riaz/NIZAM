<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('queue_id')->constrained('queues')->cascadeOnDelete();
            $table->foreignUuid('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->integer('priority')->default(0);
            $table->timestamps();
            $table->unique(['queue_id', 'agent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_members');
    }
};
