<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('media') || ! Schema::hasColumn('media', 'model_id')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            $columnType = DB::scalar(<<<'SQL'
                select data_type
                from information_schema.columns
                where table_schema = current_schema()
                  and table_name = 'media'
                  and column_name = 'model_id'
            SQL);

            if (! in_array($columnType, ['character varying', 'text'], true)) {
                DB::statement('ALTER TABLE media ALTER COLUMN model_id TYPE varchar(255) USING model_id::text');
            }

            return;
        }

        if ($driver === 'mysql') {
            $columnType = DB::scalar(<<<'SQL'
                select data_type
                from information_schema.columns
                where table_schema = database()
                  and table_name = 'media'
                  and column_name = 'model_id'
            SQL);

            if (! in_array($columnType, ['varchar', 'text'], true)) {
                DB::statement('ALTER TABLE media MODIFY model_id varchar(255) NOT NULL');
            }
        }
    }

    public function down(): void
    {
    }
};
