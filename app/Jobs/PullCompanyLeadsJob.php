<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\CompanyLeadsSyncer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

class PullCompanyLeadsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Generous ceiling for a multi-page pull — well above the queue worker's
     * default 60s so a company with many pages isn't killed mid-sync.
     */
    public int $timeout = 300;

    public function __construct(public readonly Company $company) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(CompanyLeadsSyncer $syncer): void
    {
        $result = $syncer->sync($this->company);

        logger($result);

        if (! $result['success']) {
            // CompanyLeadsSyncer never throws — it always returns a result array so the
            // synchronous "Pull data" button gets an immediate friendly message instead
            // of a 500. For the queued path, throw here instead so $tries/backoff above
            // actually get a chance to retry a transient failure.
            throw new RuntimeException($result['message']);
        }
    }
}
