<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gateways', function (Blueprint $table) {
            $table->boolean('register')->default(true)->after('transport');
            $table->string('proxy')->nullable()->after('host');
            $table->string('register_proxy')->nullable()->after('proxy');
            $table->string('from_domain')->nullable()->after('realm');
            $table->string('extension')->nullable()->after('username');
            $table->unsignedInteger('expire_seconds')->default(3600)->after('allow_transcoding');
            $table->unsignedInteger('retry_seconds')->default(30)->after('expire_seconds');
            $table->boolean('caller_id_in_from')->default(true)->after('retry_seconds');
            $table->string('profile')->default('external')->after('caller_id_in_from');
        });

        Schema::create('bridges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('bridge_type')->default('gateway');
            $table->foreignUuid('gateway_id')->nullable()->constrained('gateways')->nullOnDelete();
            $table->string('destination_template');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bridges');

        Schema::table('gateways', function (Blueprint $table) {
            $table->dropColumn([
                'register',
                'proxy',
                'register_proxy',
                'from_domain',
                'extension',
                'expire_seconds',
                'retry_seconds',
                'caller_id_in_from',
                'profile',
            ]);
        });
    }
};
