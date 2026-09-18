<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ledger of spooled call detail records the ingester has already dealt with.
 *
 * Keyed on the file name because mod_xml_cdr names each record after the call it
 * describes (`a_<uuid>.cdr.xml`), which is already unique and already the record's
 * identity. Hashing the contents to build a key would mean reading every
 * candidate file in full on every pass, which is the single most expensive thing
 * the scan could do and buys nothing: FreeSWITCH writes each record once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processed_cdr_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('file_name')->unique();
            $table->string('file_path');
            $table->string('status');

            // Attempts are counted so a transient failure — an unreachable
            // database, an organization not created yet — is retried rather than
            // costing the call, and a genuinely bad record is given up on rather
            // than retried forever.
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('last_attempted_at')->nullable();

            $table->string('call_uuid')->nullable();
            $table->text('error_message')->nullable();
            $table->string('quarantine_reason')->nullable();
            $table->string('quarantine_path')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // Discovery asks for the records still worth attempting.
            $table->index(['status', 'attempts']);
            $table->index('call_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_cdr_files');
    }
};
