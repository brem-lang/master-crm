<?php

namespace App\Jobs;

use App\Models\Company;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PullAllCompaniesLeadsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Company::where('is_active', true)
            ->get()
            ->each(fn (Company $company) => PullCompanyLeadsJob::dispatch($company));
    }
}
