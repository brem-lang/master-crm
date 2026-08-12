<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\JobRun;
use App\Services\CompanyDirectorySyncer;
use App\Services\CompanyLeadsSyncer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

class PullCompanyLeadsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Generous ceiling for a multi-page pull — well above the queue worker's
     * default 60s so a company with many pages isn't killed mid-sync.
     */
    public int $timeout = 300;

    /**
     * How long the uniqueness lock is held, in seconds. Kept a bit above
     * $timeout so a legitimately-still-running sync never has its lock
     * expire and let a duplicate slip in behind it.
     */
    public int $uniqueFor = 360;

    public function __construct(public readonly Company $company) {}

    /**
     * One company's pull can never overlap itself — a second scheduler tick
     * (or a second queue worker) dispatching for the same company while an
     * earlier pull is still queued/running is dropped, not duplicated.
     */
    public function uniqueId(): string
    {
        return (string) $this->company->id;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(CompanyLeadsSyncer $syncer, CompanyDirectorySyncer $directorySyncer): void
    {
        if (! $this->company->is_active) {
            logger("Skipping lead sync for inactive company #{$this->company->id}.");

            return;
        }

        $result = $syncer->sync($this->company);

        logger($result);

        $this->recordJobRun($result);

        if (filled($this->company->affiliates_url)) {
            $this->recordJobRun($directorySyncer->syncAffiliates($this->company));
        }

        if (filled($this->company->advertisers_url)) {
            $this->recordJobRun($directorySyncer->syncAdvertisers($this->company));
        }

        if (filled($this->company->distribution_rules_url)) {
            $this->recordJobRun($directorySyncer->syncDistributionRules($this->company));
        }

        if (! $result['success']) {
            // CompanyLeadsSyncer never throws — it always returns a result array so the
            // synchronous "Pull data" button gets an immediate friendly message instead
            // of a 500. For the queued path, throw here instead so $tries/backoff above
            // actually get a chance to retry a transient failure.
            throw new RuntimeException($result['message']);
        }
    }

    /**
     * @param  array{success: bool, pulled: int, deleted: int, message: string}  $result
     */
    private function recordJobRun(array $result): void
    {
        JobRun::create([
            'company_id' => $this->company->id,
            'triggered_by' => 'scheduled',
            'success' => $result['success'],
            'pulled' => $result['pulled'],
            'deleted' => $result['deleted'] ?? 0,
            'message' => $result['message'],
            'attempt' => $this->attempts(),
        ]);
    }
}
