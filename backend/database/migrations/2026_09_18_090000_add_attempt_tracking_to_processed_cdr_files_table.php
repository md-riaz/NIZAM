<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records how many times a spooled CDR has been attempted.
 *
 * A failure used to be final: the ledger row was keyed by path and checksum, and
 * discovery skipped anything with a row regardless of its status. So a record
 * that failed once for a transient reason — the database briefly unreachable, an
 * organization not yet created — was never tried again, and because deletion only
 * happens on success its file stayed in the working directory forever, re-hashed
 * and re-queried on every later pass.
 *
 * With an attempt count a transient failure is retried and a genuine one is
 * quarantined after a bounded number of tries, which is the behaviour the same
 * ledger was always meant to express.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processed_cdr_files', function (Blueprint $table) {
            $table->unsignedSmallInteger('attempts')->default(0)->after('status');
            $table->timestamp('last_attempted_at')->nullable()->after('attempts');
            $table->string('quarantine_reason')->nullable()->after('error_message');
            $table->string('quarantine_path')->nullable()->after('quarantine_reason');
        });

        Schema::table('processed_cdr_files', function (Blueprint $table) {
            // Discovery asks for failed rows that are still worth retrying, so
            // that pair is what needs to be cheap to look up.
            $table->index(['status', 'attempts']);
        });
    }

    public function down(): void
    {
        Schema::table('processed_cdr_files', function (Blueprint $table) {
            $table->dropIndex(['status', 'attempts']);
            $table->dropColumn(['attempts', 'last_attempted_at', 'quarantine_reason', 'quarantine_path']);
        });
    }
};
