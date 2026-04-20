<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dids')) {
            Schema::table('dids', function (Blueprint $table) {
                $table->dropUnique(['organization_id', 'number', 'gateway_id', 'gateway_registration_id']);
                $table->unique(['organization_id', 'number', 'gateway_id']);
            });

            if (Schema::hasColumn('dids', 'gateway_registration_id')) {
                Schema::table('dids', function (Blueprint $table) {
                    $table->dropConstrainedForeignId('gateway_registration_id');
                });
            }
        }

        Schema::dropIfExists('gateway_registrations');
    }

    public function down(): void
    {
        if (! Schema::hasTable('gateway_registrations')) {
            Schema::create('gateway_registrations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('gateway_id')->constrained('gateways')->cascadeOnDelete();
                $table->string('registration_identifier')->unique();
                $table->string('username')->nullable();
                $table->string('realm')->nullable();
                $table->string('proxy')->nullable();
                $table->string('transport')->nullable();
                $table->string('status')->default('unknown')->index();
                $table->timestamp('last_registered_at')->nullable();
                $table->timestamp('last_failed_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['gateway_id', 'status']);
            });
        }

        if (Schema::hasTable('dids')) {
            Schema::table('dids', function (Blueprint $table) {
                $table->dropUnique(['organization_id', 'number', 'gateway_id']);
            });

            if (! Schema::hasColumn('dids', 'gateway_registration_id')) {
                Schema::table('dids', function (Blueprint $table) {
                    $table->foreignUuid('gateway_registration_id')->nullable()->after('gateway_id')->constrained('gateway_registrations')->nullOnDelete();
                });
            }

            Schema::table('dids', function (Blueprint $table) {
                $table->unique(['organization_id', 'number', 'gateway_id', 'gateway_registration_id']);
            });
        }
    }
};
