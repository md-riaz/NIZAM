<?php

namespace App\Jobs;

use App\Models\CallDetailRecord;
use App\Services\Cdr\CdrEnrichmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnrichCdrJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 30;

    public function __construct(
        public CallDetailRecord $cdr
    ) {}

    /**
     * Execute the job.
     */
    public function handle(CdrEnrichmentService $enrichmentService): void
    {
        try {
            $enrichmentService->enrich($this->cdr);
        } catch (\Exception $e) {
            Log::error('CDR enrichment failed', [
                'cdr_id' => $this->cdr->id,
                'uuid' => $this->cdr->uuid,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
