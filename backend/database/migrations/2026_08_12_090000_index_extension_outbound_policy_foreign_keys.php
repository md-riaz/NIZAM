<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index the outbound-policy foreign keys on `extensions`.
 *
 * `default_outbound_did_id` and `default_outbound_gateway_id` were added with
 * `constrained()` but no index. Postgres does not index the referencing side of
 * a foreign key automatically, so every outbound originate resolves the default
 * DID and gateway with a sequential scan, and deleting a DID or gateway scans
 * `extensions` to enforce the constraint.
 *
 * `device_profile_id` has the same problem in a subtler form: its only index is
 * the composite `(organization_id, device_profile_id)`, where it is not the
 * leading column and so cannot serve a lookup by device alone.
 */
return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $columns = [
        'default_outbound_did_id',
        'default_outbound_gateway_id',
        'device_profile_id',
    ];

    public function up(): void
    {
        Schema::table('extensions', function (Blueprint $table) {
            foreach ($this->columns as $column) {
                $table->index($column, $this->indexName($column));
            }
        });
    }

    public function down(): void
    {
        Schema::table('extensions', function (Blueprint $table) {
            foreach ($this->columns as $column) {
                $table->dropIndex($this->indexName($column));
            }
        });
    }

    /**
     * Named explicitly so `down()` drops exactly what `up()` created, rather
     * than relying on the generated name matching.
     */
    private function indexName(string $column): string
    {
        return 'extensions_'.$column.'_index';
    }
};
