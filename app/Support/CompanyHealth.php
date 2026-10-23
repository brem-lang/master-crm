<?php

namespace App\Support;

use App\Models\Company;
use App\Models\JobRun;
use App\Services\WebsiteHealthChecker;
use Illuminate\Support\Collection;

class CompanyHealth
{
    public function __construct(private readonly WebsiteHealthChecker $healthChecker) {}

    /**
     * Attach website reachability and sync failure-streak data to each company.
     *
     * @param  Collection<int, Company>  $companies
     */
    public function attach(Collection $companies): void
    {
        $statuses = $this->healthChecker->statusFor($companies);

        $streaks = $this->failureStreaks($companies->pluck('id'));

        $companies->each(function (Company $company) use ($statuses, $streaks) {
            $company->setAttribute('website_status', $statuses[$company->id] ?? null);
            $company->setAttribute('failure_streak', (int) ($streaks[$company->id] ?? 0));
        });
    }

    /**
     * Count consecutive failed runs per company since its most recent success.
     *
     * Aggregated in SQL so the full run history is never loaded into memory.
     *
     * @param  Collection<int, int>  $companyIds
     * @return Collection<int, int>
     */
    private function failureStreaks(Collection $companyIds): Collection
    {
        $lastSuccess = JobRun::query()
            ->whereIn('company_id', $companyIds)
            ->where('success', true)
            ->groupBy('company_id')
            ->selectRaw('company_id, max(created_at) as last_success_at');

        return JobRun::query()
            ->from('job_runs as runs')
            ->leftJoinSub($lastSuccess, 'last_success', 'last_success.company_id', '=', 'runs.company_id')
            ->whereIn('runs.company_id', $companyIds)
            ->where('runs.success', false)
            ->where(fn ($query) => $query->whereNull('last_success.last_success_at')
                ->orWhereColumn('runs.created_at', '>', 'last_success.last_success_at'))
            ->groupBy('runs.company_id')
            ->selectRaw('runs.company_id, count(*) as streak')
            ->pluck('streak', 'company_id');
    }
}
