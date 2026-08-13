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
     * @return array{success: bool, total?: int, all_rules_count?: int, page: int, pages: int, next_cursor?: ?string, next_since?: ?string, data: list<array<string, mixed>>}
     *
     * @throws ChildCrmSyncException
     */
    public function fetchDistributionRulesPage(Company $company, int $page, int $limit = 100, ?string $since = null): array
    {
        return $this->fetchPage($company, $company->distribution_rules_url, 'No distribution-rules API URL is configured', $page, $limit, $since);
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
     * Sends a manually-triggered test lead to one of the company's advertisers.
     * Unlike every other client method here, a non-2xx reply isn't necessarily
     * a failure worth hiding behind a generic message — the child API's own
     * body (its `message`, on both success and error) is what the admin needs
     * to see, so this returns the status/body pair as-is rather than throwing.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    public function sendTestLead(Company $company, array $payload): array
    {
        if (blank($company->send_test_lead_url)) {
            return [
                'status' => 422,
                'body' => ['success' => false, 'message' => "No send-test-lead API URL is configured for {$company->name}."],
            ];
        }

        try {
            $response = $this->postJson($company, $company->send_test_lead_url, $payload);
        } catch (ChildCrmSyncException $e) {
            return ['status' => 502, 'body' => ['success' => false, 'message' => $e->getMessage()]];
        }

        $body = $response->json();

        if (! is_array($body)) {
            return [
                'status' => 502,
                'body' => ['success' => false, 'message' => "{$company->name}'s API returned an unreadable response."],
            ];
        }

        return ['status' => $response->status(), 'body' => $body];
    }

    /**
     * Notifies the child CRM that one of its leads' FTD has been released.
     * Same non-throwing shape as `sendTestLead()` — the child API's own
     * message is what the admin needs to see on failure, not a generic one.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    public function releaseFtd(Company $company, array $payload): array
    {
        if (blank($company->release_ftd_url)) {
            return [
                'status' => 422,
                'body' => ['success' => false, 'message' => "No release-FTD API URL is configured for {$company->name}."],
            ];
        }

        try {
            $response = $this->postJson($company, $company->release_ftd_url, $payload);
        } catch (ChildCrmSyncException $e) {
            return ['status' => 502, 'body' => ['success' => false, 'message' => $e->getMessage()]];
        }

        $body = $response->json();

        if (! is_array($body)) {
            return [
                'status' => 502,
                'body' => ['success' => false, 'message' => "{$company->name}'s API returned an unreadable response."],
            ];
        }

        return ['status' => $response->status(), 'body' => $body];
    }

    /**
     * (Re)submits a lead to one of the company's advertisers via a chosen
     * affiliate. Same non-throwing shape as `sendTestLead()`/`releaseFtd()` —
     * the child API's own message (e.g. a 409 duplicate-email/IP rejection)
     * is what the admin needs to see, not a generic failure.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    public function resendLead(Company $company, array $payload): array
    {
        if (blank($company->send_lead_url)) {
            return [
                'status' => 422,
                'body' => ['success' => false, 'message' => "No send-lead API URL is configured for {$company->name}."],
            ];
        }

        try {
            $response = $this->postJson($company, $company->send_lead_url, $payload);
        } catch (ChildCrmSyncException $e) {
            return ['status' => 502, 'body' => ['success' => false, 'message' => $e->getMessage()]];
        }

        $body = $response->json();

        if (! is_array($body)) {
            return [
                'status' => 502,
                'body' => ['success' => false, 'message' => "{$company->name}'s API returned an unreadable response."],
            ];
        }

        return ['status' => $response->status(), 'body' => $body];
    }

    /**
     * Re-submits a rejected lead to the child CRM's `resend-lead` endpoint,
     * routed to a different affiliate/advertiser within the same company —
     * unlike {@see resendLead()}, there's no cross-company redirect here,
     * since a rejected lead only exists in its own child CRM.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    public function resendRejectedLead(Company $company, array $payload): array
    {
        if (blank($company->resend_lead_url)) {
            return [
                'status' => 422,
                'body' => ['success' => false, 'message' => "No resend-lead API URL is configured for {$company->name}."],
            ];
        }

        try {
            $response = $this->postJson($company, $company->resend_lead_url, $payload);
        } catch (ChildCrmSyncException $e) {
            return ['status' => 502, 'body' => ['success' => false, 'message' => $e->getMessage()]];
        }

        $body = $response->json();

        if (! is_array($body)) {
            return [
                'status' => 502,
                'body' => ['success' => false, 'message' => "{$company->name}'s API returned an unreadable response."],
            ];
        }

        return ['status' => $response->status(), 'body' => $body];
    }

    /**
     * Notifies the child CRM that one of its affiliates' active/inactive
     * status has changed. Same non-throwing shape as `releaseFtd()` — the
     * child API's own message is what the admin needs to see on failure.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    public function updateAffiliateStatus(Company $company, array $payload): array
    {
        if (blank($company->update_affiliate_status_url)) {
            return [
                'status' => 422,
                'body' => ['success' => false, 'message' => "No update-affiliate-status API URL is configured for {$company->name}."],
            ];
        }

        try {
            $response = $this->postJson($company, $company->update_affiliate_status_url, $payload);
        } catch (ChildCrmSyncException $e) {
            return ['status' => 502, 'body' => ['success' => false, 'message' => $e->getMessage()]];
        }

        $body = $response->json();

        if (! is_array($body)) {
            return [
                'status' => 502,
                'body' => ['success' => false, 'message' => "{$company->name}'s API returned an unreadable response."],
            ];
        }

        return ['status' => $response->status(), 'body' => $body];
    }

    /**
     * Pushes an edit to one of the child CRM's own distribution rules — any
     * subset of the editable fields may be sent. Same non-throwing shape as
     * `updateAffiliateStatus()`: the child API's own response (including its
     * field-level validation errors) is relayed to the admin as-is.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    public function updateDistributionRule(Company $company, array $payload): array
    {
        if (blank($company->update_distribution_rule_url)) {
            return [
                'status' => 422,
                'body' => ['success' => false, 'message' => "No update-distribution-rule API URL is configured for {$company->name}."],
            ];
        }

        try {
            $response = $this->postJson($company, $company->update_distribution_rule_url, $payload);
        } catch (ChildCrmSyncException $e) {
            return ['status' => 502, 'body' => ['success' => false, 'message' => $e->getMessage()]];
        }

        $body = $response->json();

        if (! is_array($body)) {
            return [
                'status' => 502,
                'body' => ['success' => false, 'message' => "{$company->name}'s API returned an unreadable response."],
            ];
        }

        return ['status' => $response->status(), 'body' => $body];
    }

    /**
     * Pushes an edit to one of the child CRM's own advertisers — any subset
     * of the editable fields may be sent. Same non-throwing shape as
     * `updateDistributionRule()`: the child API's own response (including
     * its field-level validation errors) is relayed to the admin as-is.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    public function updateAdvertiser(Company $company, array $payload): array
    {
        if (blank($company->update_advertiser_url)) {
            return [
                'status' => 422,
                'body' => ['success' => false, 'message' => "No update-advertiser API URL is configured for {$company->name}."],
            ];
        }

        try {
            $response = $this->postJson($company, $company->update_advertiser_url, $payload);
        } catch (ChildCrmSyncException $e) {
            return ['status' => 502, 'body' => ['success' => false, 'message' => $e->getMessage()]];
        }

        $body = $response->json();

        if (! is_array($body)) {
            return [
                'status' => 502,
                'body' => ['success' => false, 'message' => "{$company->name}'s API returned an unreadable response."],
            ];
        }

        return ['status' => $response->status(), 'body' => $body];
    }

    /**
     * Pushes an edit to one of the child CRM's own affiliates — any subset
     * of the editable fields may be sent. Same non-throwing shape as
     * `updateAdvertiser()`: the child API's own response (including its
     * field-level validation errors) is relayed to the admin as-is.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    public function updateAffiliate(Company $company, array $payload): array
    {
        if (blank($company->update_affiliate_url)) {
            return [
                'status' => 422,
                'body' => ['success' => false, 'message' => "No update-affiliate API URL is configured for {$company->name}."],
            ];
        }

        try {
            $response = $this->postJson($company, $company->update_affiliate_url, $payload);
        } catch (ChildCrmSyncException $e) {
            return ['status' => 502, 'body' => ['success' => false, 'message' => $e->getMessage()]];
        }

        $body = $response->json();

        if (! is_array($body)) {
            return [
                'status' => 502,
                'body' => ['success' => false, 'message' => "{$company->name}'s API returned an unreadable response."],
            ];
        }

        return ['status' => $response->status(), 'body' => $body];
    }

    /**
     * Live-fetches every affiliate with IP-whitelist enforcement turned on
     * for a company — a small reporting query, not part of the regular
     * directory sync, so nothing is persisted locally; the child CRM's own
     * `name`/`allowed_ips` in the response are shown as-is.
     *
     * @return array{success: bool, total?: int, page: int, pages: int, count?: int, data: list<array<string, mixed>>}
     *
     * @throws ChildCrmSyncException
     */
    public function fetchWhitelistedAffiliateIps(Company $company, int $page = 0, int $limit = 100): array
    {
        if (blank($company->get_affiliate_whitelisted_ips_url)) {
            throw new ChildCrmSyncException("No affiliate-whitelisted-ips API URL is configured for {$company->name}.");
        }

        // The configured URL is the complete, ready-to-call endpoint (may already carry
        // its own query string) — merge rather than replace it, same as fetchPage().
        $urlParts = parse_url($company->get_affiliate_whitelisted_ips_url);
        parse_str($urlParts['query'] ?? '', $existingQuery);
        $query = [...$existingQuery, 'ip_whitelist_required' => 'true', 'page' => $page, 'limit' => $limit];

        $response = $this->request($company, strtok($company->get_affiliate_whitelisted_ips_url, '?'), $query);
        $body = $response->json();

        if (! is_array($body)) {
            throw new ChildCrmSyncException("{$company->name}'s API returned an unreadable response.");
        }

        return $body;
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
