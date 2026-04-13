<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('call_detail_records')) {
            Schema::table('call_detail_records', function (Blueprint $table): void {
                $table->index(['tenant_id', 'caller_id_number', 'start_stamp'], 'cdr_tenant_caller_start_idx');
            });
        }

        if (Schema::hasTable('recordings')) {
            Schema::table('recordings', function (Blueprint $table): void {
                $table->index(['tenant_id', 'caller_id_number', 'created_at'], 'recordings_tenant_caller_created_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('recordings')) {
            Schema::table('recordings', function (Blueprint $table): void {
                $table->dropIndex('recordings_tenant_caller_created_idx');
            });
        }

        if (Schema::hasTable('call_detail_records')) {
            Schema::table('call_detail_records', function (Blueprint $table): void {
                $table->dropIndex('cdr_tenant_caller_start_idx');
            });
        }
    }
};
