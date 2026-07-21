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
