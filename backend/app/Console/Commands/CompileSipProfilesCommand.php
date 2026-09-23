<?php

namespace App\Console\Commands;

use App\Services\SipProfileCompiler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Write the Sofia profiles FreeSWITCH needs in order to start.
 *
 * Until now the only thing that wrote them was a model observer, firing when a
 * profile or one of its settings was saved. A deployment that never touched a
 * profile therefore had none on disk, and FreeSWITCH refuses to start without
 * `internal.xml` and `external.xml` — it exits, the container restarts, and it
 * does so again, forever, with nothing in the database to explain it.
 *
 * Boot has to be able to ask for them directly, which is what this is for. It
 * also reports on the result, because compiling successfully and producing a
 * tree FreeSWITCH can start from are not the same thing.
 */
class CompileSipProfilesCommand extends Command
{
    protected $signature = 'nizam:compile-sip-profiles';

    protected $description = 'Compile the active SIP profiles to the directory FreeSWITCH reads at startup';

    /**
     * The profiles FreeSWITCH's own preflight insists on, by file name.
     *
     * @var array<int, string>
     */
    protected const REQUIRED_PROFILES = ['internal', 'external'];

    public function handle(SipProfileCompiler $compiler): int
    {
        $compiler->compileAllToDisk();

        $directory = rtrim((string) config(
            'telephony.sip_profile_provisioning.directory',
            storage_path('app/freeswitch/sip_profiles')
        ), '/');

        $missing = [];

        foreach (self::REQUIRED_PROFILES as $name) {
            if (! File::exists($directory.'/'.$name.'.xml')) {
                $missing[] = $name;
            }
        }

        if ($missing !== []) {
            // Counting the profiles that compiled is not enough: the compiler
            // writes only active ones and deletes the rest, so deactivating a
            // single profile removes its file. One of the two still compiles,
            // the count is non-zero, and the run would look like a success
            // while leaving FreeSWITCH unable to start for the other.
            //
            // Nothing is reactivated here. An administrator turning a profile
            // off is a decision, and quietly reversing it would be worse than
            // saying plainly what it costs.
            $this->error(sprintf(
                'Missing required SIP profile(s): %s. FreeSWITCH will not start without %s — '
                .'check that each is present and active.',
                implode(', ', $missing),
                implode(' and ', array_map(fn ($n) => $n.'.xml', self::REQUIRED_PROFILES))
            ));

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Compiled SIP profiles to %s; %s present.',
            $directory,
            implode(' and ', array_map(fn ($n) => $n.'.xml', self::REQUIRED_PROFILES))
        ));

        return self::SUCCESS;
    }
}
