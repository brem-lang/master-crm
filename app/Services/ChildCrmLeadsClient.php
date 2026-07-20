<?php

namespace App\Services;

use App\Models\Company;
use App\Support\ChildCrmSyncException;
use App\Support\SafeUrl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ChildCrmLeadsClient
{
    private const CONNECT_TIMEOUT_SECONDS = 2;

    private const REQUEST_TIMEOUT_SECONDS = 5;

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

    /**
     * SSRF-guarded, timeout-bounded GET against one of the company's child-CRM
     * endpoints, authenticated with its api_key.
     *
     * @param  array<string, mixed>  $query
     *
     * @throws ChildCrmSyncException
     */
    private function request(Company $company, string $url, array $query = []): Response
    {
        if (! SafeUrl::isSafe($url)) {
            throw new ChildCrmSyncException("The API address for {$company->name} looks unsafe or invalid, so we didn't contact it.");
        }

        try {
            $response = Http::withHeaders([
                'Api-Key' => $company->api_key,
                'Authorization' => "Bearer {$company->api_key}",
            ])
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->timeout(self::REQUEST_TIMEOUT_SECONDS)
                ->withOptions(['allow_redirects' => false])
                ->get($url, $query);
        } catch (ConnectionException) {
            throw new ChildCrmSyncException("Could not reach {$company->name}'s API. Check the API URL and try again.");
        }

        if (! $response->successful()) {
            throw new ChildCrmSyncException($this->describeStatus($company, $response->status()));
        }

        return $response;
    }

    /**
     * Translate an HTTP status code into a plain-language summary for the admin toast.
     */
    private function describeStatus(Company $company, int $status): string
    {
        return match (true) {
            $status === 401 || $status === 403 => "Could not connect to {$company->name}'s API — the API key was rejected. Double-check the key and try again.",
            $status === 404 => "{$company->name}'s API address could not be found. Double-check the API URL.",
            $status === 429 => "{$company->name}'s API is temporarily limiting requests. Try again shortly.",
            $status >= 500 => "{$company->name}'s API is currently having problems. Try again later.",
            default => "{$company->name}'s API returned an unexpected response (code {$status}).",
        };
    }
}
