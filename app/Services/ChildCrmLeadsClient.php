<?php

namespace App\Services;

use App\Models\Company;
use App\Services\Concerns\MakesChildCrmRequests;
use App\Support\ChildCrmSyncException;

class ChildCrmLeadsClient
{
    use MakesChildCrmRequests;

    /**
     * Fetch one page of leads from a company's child CRM.
     *
     * @return array{success: bool, total?: int, all_leads_count?: int, page: int, pages: int, next_cursor?: ?string, next_since?: ?string, data: list<array<string, mixed>>}
     *
     * @throws ChildCrmSyncException
     */
    public function fetchPage(Company $company, int $page, int $limit = 100, ?string $since = null): array
    {
        // api_url is the complete, ready-to-call leads endpoint (may already carry its
        // own query string, e.g. `?includeRejected=1`) — merge rather than replace it.
        $urlParts = parse_url($company->api_url);
        parse_str($urlParts['query'] ?? '', $existingQuery);
        $query = array_filter(
            [...$existingQuery, 'page' => $page, 'limit' => $limit, 'since' => $since],
            fn ($value) => $value !== null,
        );

        $response = $this->request($company, strtok($company->api_url, '?'), $query);
        $body = $response->json();

        if (! is_array($body)) {
            throw new ChildCrmSyncException("{$company->name}'s API returned an unreadable response.");
        }

        return $body;
    }

    /**
     * Fetch the authoritative total lead count from the company's dedicated,
     * lightweight count endpoint — kept separate from `fetchPage()` so checking
     * "is there anything new" never has to pay for a full leads-listing call.
     *
     * @throws ChildCrmSyncException
     */
    public function fetchLeadCount(Company $company): int
    {
        if (blank($company->leads_count_url)) {
            throw new ChildCrmSyncException("No leads-count API URL is configured for {$company->name}.");
        }

        $response = $this->request($company, $company->leads_count_url);
        $body = $response->json();

        if (! is_array($body) || ! array_key_exists('total_leads', $body)) {
            throw new ChildCrmSyncException("{$company->name}'s leads-count API returned an unreadable response.");
        }

        return (int) $body['total_leads'];
    }
}
