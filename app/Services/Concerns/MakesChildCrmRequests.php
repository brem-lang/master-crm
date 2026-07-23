<?php

namespace App\Services\Concerns;

use App\Models\Company;
use App\Support\ChildCrmSyncException;
use App\Support\SafeUrl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * SSRF-guarded, timeout-bounded GET requests against a company's child-CRM
 * endpoints, authenticated with its api_key. Shared by every child-CRM client
 * so the SSRF guard has exactly one implementation.
 */
trait MakesChildCrmRequests
{
    private const CONNECT_TIMEOUT_SECONDS = 2;

    private const REQUEST_TIMEOUT_SECONDS = 5;

    /**
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
     * POST-and-inspect, unlike `request()` above — that one throws on any
     * non-2xx status, which is right for background syncs where only a
     * generic toast is shown. Callers here (e.g. relaying a child API's own
     * 400/401/404 body straight to an admin) need the raw response on every
     * HTTP-level reply, so this only throws for SSRF rejection or a genuine
     * connection failure — both truly exceptional, unlike a 4xx reply.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ChildCrmSyncException
     */
    private function postJson(Company $company, string $url, array $payload): Response
    {
        if (! SafeUrl::isSafe($url)) {
            throw new ChildCrmSyncException("The API address for {$company->name} looks unsafe or invalid, so we didn't contact it.");
        }

        try {
            return Http::withHeaders([
                'Api-Key' => $company->api_key,
                'Authorization' => "Bearer {$company->api_key}",
            ])
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->timeout(self::REQUEST_TIMEOUT_SECONDS)
                ->withOptions(['allow_redirects' => false])
                ->post($url, $payload);
        } catch (ConnectionException) {
            throw new ChildCrmSyncException("Could not reach {$company->name}'s API. Check the API URL and try again.");
        }
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
