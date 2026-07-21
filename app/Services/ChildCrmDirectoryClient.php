<?php

namespace App\Services;

use App\Models\Company;
use App\Services\Concerns\MakesChildCrmRequests;
use App\Support\ChildCrmSyncException;

class ChildCrmDirectoryClient
{
    use MakesChildCrmRequests;

    /**
     * @return array{success: bool, total?: int, all_affiliates_count?: int, page: int, pages: int, next_cursor?: ?string, next_since?: ?string, data: list<array<string, mixed>>}
     *
     * @throws ChildCrmSyncException
     */
    public function fetchAffiliatesPage(Company $company, int $page, int $limit = 100, ?string $since = null): array
    {
        return $this->fetchPage($company, $company->affiliates_url, 'No affiliates API URL is configured', $page, $limit, $since);
    }

    /**
     * @return array{success: bool, total?: int, all_advertisers_count?: int, page: int, pages: int, next_cursor?: ?string, next_since?: ?string, data: list<array<string, mixed>>}
     *
     * @throws ChildCrmSyncException
     */
    public function fetchAdvertisersPage(Company $company, int $page, int $limit = 100, ?string $since = null): array
    {
        return $this->fetchPage($company, $company->advertisers_url, 'No advertisers API URL is configured', $page, $limit, $since);
    }

    /**
     * Fetch the authoritative affiliate count from the company's dedicated,
     * lightweight count endpoint — mirrors `ChildCrmLeadsClient::fetchLeadCount()`.
     *
     * @throws ChildCrmSyncException
     */
    public function fetchAffiliateCount(Company $company): int
    {
        return $this->fetchCount($company, $company->affiliate_count_api_url, 'No affiliate-count API URL is configured', 'total_affiliates');
    }

    /**
     * @throws ChildCrmSyncException
     */
    public function fetchAdvertiserCount(Company $company): int
    {
        return $this->fetchCount($company, $company->advertiser_count_api_url, 'No advertiser-count API URL is configured', 'total_advertisers');
    }

    /**
     * @throws ChildCrmSyncException
     */
    private function fetchCount(Company $company, ?string $url, string $missingUrlMessage, string $countKey): int
    {
        if (blank($url)) {
            throw new ChildCrmSyncException("{$missingUrlMessage} for {$company->name}.");
        }

        $response = $this->request($company, $url);
        $body = $response->json();

        if (! is_array($body) || ! array_key_exists($countKey, $body)) {
            throw new ChildCrmSyncException("{$company->name}'s count API returned an unreadable response.");
        }

        return (int) $body[$countKey];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ChildCrmSyncException
     */
    private function fetchPage(Company $company, ?string $url, string $missingUrlMessage, int $page, int $limit, ?string $since): array
    {
        if (blank($url)) {
            throw new ChildCrmSyncException("{$missingUrlMessage} for {$company->name}.");
        }

        // The configured URL is the complete, ready-to-call endpoint (may already carry
        // its own query string) — merge rather than replace it, same as the leads client.
        $urlParts = parse_url($url);
        parse_str($urlParts['query'] ?? '', $existingQuery);
        $query = array_filter(
            [...$existingQuery, 'page' => $page, 'limit' => $limit, 'since' => $since],
            fn ($value) => $value !== null,
        );

        $response = $this->request($company, strtok($url, '?'), $query);
        $body = $response->json();

        if (! is_array($body)) {
            throw new ChildCrmSyncException("{$company->name}'s API returned an unreadable response.");
        }

        return $body;
    }
}
