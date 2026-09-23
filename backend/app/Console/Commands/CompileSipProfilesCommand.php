<?php

namespace App\Console\Commands;

use App\Models\SipProfile;
use App\Services\SipProfileCompiler;
use Illuminate\Console\Command;

/**
 * Write the Sofia profiles FreeSWITCH needs in order to start.
 *
 * Until now the only thing that wrote them was a model observer, firing when a
 * profile or one of its settings was saved. A deployment that never touched a
 * profile therefore had none on disk, and FreeSWITCH refuses to start without
 * `internal.xml` and `external.xml` — it exits, the container restarts, and it
 * does so again, forever, with nothing in the database to explain it.
 *
 * Boot has to be able to ask for them directly, which is what this is for.
 */
class CompileSipProfilesCommand extends Command
{
    protected $signature = 'nizam:compile-sip-profiles';

    protected $description = 'Compile the active SIP profiles to the directory FreeSWITCH reads at startup';

    public function handle(SipProfileCompiler $compiler): int
    {
        $active = SipProfile::query()->where('is_active', true)->count();

        if ($active === 0) {
            // Not an error in itself, but FreeSWITCH will not start, so say so
            // here rather than leaving it to be diagnosed from a restart loop.
            $this->warn('No active SIP profiles to compile. FreeSWITCH will not start without at least internal and external — run the SipProfileSeeder.');

            return self::SUCCESS;
        }

        $compiler->compileAllToDisk();

        $this->info(sprintf('Compiled %d SIP profile(s) to disk.', $active));

        return self::SUCCESS;
    }
}
