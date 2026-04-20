<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $this->normalizeUserRoles();
        $this->createSystemSettingsTable();
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }

    private function normalizeUserRoles(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'role')) {
            return;
        }

        DB::table('users')->update([
            'role' => DB::raw("case
                when role = 'superadmin' then 'superadmin'
                when role = 'admin' then 'admin'
                when role = 'owner' then 'admin'
                else 'agent'
            end"),
        ]);
    }

    private function createSystemSettingsTable(): void
    {
        if (Schema::hasTable('system_settings')) {
            return;
        }

        Schema::create('system_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->nullable()->constrained('organizations')->cascadeOnDelete();
            $table->string('key');
            $table->json('value')->nullable();
            $table->string('value_type', 50)->default('json');
            $table->timestamps();

            $table->index('organization_id', 'system_settings_organization_index');
            $table->unique(['organization_id', 'key'], 'system_settings_organization_key_unique');
        });
    }

};
