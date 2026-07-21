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

        $runsByCompany = JobRun::whereIn('company_id', $companies->pluck('id'))
            ->orderByDesc('created_at')
            ->get(['company_id', 'success', 'created_at'])
            ->groupBy('company_id');

        $companies->each(function (Company $company) use ($statuses, $runsByCompany) {
            $company->setAttribute('website_status', $statuses[$company->id] ?? null);
            $company->setAttribute('failure_streak', $this->failureStreak($runsByCompany->get($company->id, collect())));
        });
    }

    /**
     * Count consecutive failed runs, newest first, until the first success.
     *
     * @param  Collection<int, JobRun>  $runs
     */
    private function failureStreak(Collection $runs): int
    {
        $streak = 0;

        foreach ($runs as $run) {
            if ($run->success) {
                break;
            }

            $streak++;
        }

        return $streak;
    }
}
