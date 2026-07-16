<?php

namespace App\Services;

use App\Models\Company;
use App\Support\SafeUrl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WebsiteHealthChecker
{
    private const CACHE_TTL_MINUTES = 5;

    private const REQUEST_TIMEOUT_SECONDS = 3;

    private const CONNECT_TIMEOUT_SECONDS = 2;

    /**
     * Resolve a reachability status ('online'|'offline'|null) for each company, keyed by id.
     *
     * @param  Collection<int, Company>  $companies
     * @return array<int, 'online'|'offline'|null>
     */
    public function statusFor(Collection $companies): array
    {
        $withWebsite = $companies->filter(fn (Company $company) => filled($company->website));

        $statuses = [];
        $pending = [];

        foreach ($withWebsite as $company) {
            $cacheKey = $this->cacheKey($company);
            $cached = Cache::get($cacheKey);

            if ($cached !== null) {
                $statuses[$company->id] = $cached;
            } else {
                $pending[] = $company;
            }
        }

        if ($pending !== []) {
            // `+` (not array spread) so integer company-id keys aren't reindexed like a list.
            $statuses = $statuses + $this->checkPending($pending);
        }

        return $statuses;
    }

    /**
     * @param  list<Company>  $companies
     * @return array<int, 'online'|'offline'>
     */
    private function checkPending(array $companies): array
    {
        $checkable = array_values(array_filter(
            $companies,
            fn (Company $company) => SafeUrl::isSafe($company->website),
        ));

        $responses = $checkable === []
            ? []
            : Http::pool(fn ($pool) => array_map(
                fn (Company $company) => $pool->as((string) $company->id)
                    ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                    ->timeout(self::REQUEST_TIMEOUT_SECONDS)
                    ->withOptions(['allow_redirects' => false])
                    ->head($company->website),
                $checkable,
            ));

        $results = [];

        foreach ($companies as $company) {
            $status = $this->resolveStatus($company, $responses[(string) $company->id] ?? null);
            $results[$company->id] = $status;
            Cache::put($this->cacheKey($company), $status, now()->addMinutes(self::CACHE_TTL_MINUTES));
        }

        return $results;
    }

    /**
     * @return 'online'|'offline'
     */
    private function resolveStatus(Company $company, mixed $response): string
    {
        if (! SafeUrl::isSafe($company->website)) {
            return 'offline';
        }

        if ($response instanceof ConnectionException) {
            return 'offline';
        }

        // A 405/501 means the server rejected HEAD; retry with GET before giving up.
        if ($response === null || in_array($response->status(), [405, 501], true)) {
            try {
                Http::connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                    ->timeout(self::REQUEST_TIMEOUT_SECONDS)
                    ->withOptions(['allow_redirects' => false])
                    ->get($company->website);

                return 'online';
            } catch (ConnectionException) {
                return 'offline';
            }
        }

        return 'online';
    }

    private function cacheKey(Company $company): string
    {
        return "company-website-status:{$company->id}";
    }
}
