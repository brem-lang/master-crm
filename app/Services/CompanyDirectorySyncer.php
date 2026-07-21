<?php

namespace App\Services;

use App\Models\Advertiser;
use App\Models\Affiliate;
use App\Models\Company;
use App\Services\Concerns\BulkUpsertsExternalRecords;
use App\Support\ChildCrmSyncException;
use Illuminate\Support\Facades\Crypt;

class CompanyDirectorySyncer
{
    use BulkUpsertsExternalRecords;

    private const LIMIT = 100;

    /**
     * Hard cap on pages processed per sync, guarding against a child API that
     * misreports an absurd page count.
     */
    private const MAX_PAGES = 500;

    private const AFFILIATE_MAPPED_KEYS = ['id', 'name', 'api_key', 'is_active'];

    private const ADVERTISER_MAPPED_KEYS = [
        'id', 'name', 'advertiser_type', 'url', 'api_key', 'is_active', 'daily_cap', 'hourly_cap', 'default_deal_type',
    ];

    public function __construct(private readonly ChildCrmDirectoryClient $client) {}

    /**
     * @return array{success: bool, pulled: int, message: string}
     */
    public function syncAffiliates(Company $company): array
    {
        return $this->sync(
            company: $company,
            urlField: 'affiliates_url',
            countUrlField: 'affiliate_count_api_url',
            sinceField: 'affiliates_last_synced_since',
            cursorField: 'affiliates_last_synced_cursor',
            lastSyncedAtField: 'affiliates_last_synced_at',
            allCountKey: 'all_affiliates_count',
            entityLabel: 'affiliates',
            model: Affiliate::class,
            fetchPage: fn (Company $c, int $page, int $limit, ?string $since) => $this->client->fetchAffiliatesPage($c, $page, $limit, $since),
            fetchCount: fn (Company $c) => $this->client->fetchAffiliateCount($c),
            mapItem: fn (array $item) => $this->mapAffiliate($item),
        );
    }

    /**
     * @return array{success: bool, pulled: int, message: string}
     */
    public function syncAdvertisers(Company $company): array
    {
        return $this->sync(
            company: $company,
            urlField: 'advertisers_url',
            countUrlField: 'advertiser_count_api_url',
            sinceField: 'advertisers_last_synced_since',
            cursorField: 'advertisers_last_synced_cursor',
            lastSyncedAtField: 'advertisers_last_synced_at',
            allCountKey: 'all_advertisers_count',
            entityLabel: 'advertisers',
            model: Advertiser::class,
            fetchPage: fn (Company $c, int $page, int $limit, ?string $since) => $this->client->fetchAdvertisersPage($c, $page, $limit, $since),
            fetchCount: fn (Company $c) => $this->client->fetchAdvertiserCount($c),
            mapItem: fn (array $item) => $this->mapAdvertiser($item),
        );
    }

    /**
     * Shared pagination/upsert loop for both affiliates and advertisers — same
     * shape as `CompanyLeadsSyncer::sync()`, parameterized over which entity.
     *
     * @param  class-string<Affiliate|Advertiser>  $model
     * @param  callable(Company, int, int, ?string): array<string, mixed>  $fetchPage
     * @param  callable(Company): int  $fetchCount
     * @param  callable(array<string, mixed>): array<string, mixed>  $mapItem
     * @return array{success: bool, pulled: int, message: string}
     */
    private function sync(
        Company $company,
        string $urlField,
        string $countUrlField,
        string $sinceField,
        string $cursorField,
        string $lastSyncedAtField,
        string $allCountKey,
        string $entityLabel,
        string $model,
        callable $fetchPage,
        callable $fetchCount,
        callable $mapItem,
    ): array {
        if (blank($company->{$urlField})) {
            return ['success' => true, 'pulled' => 0, 'message' => "No {$entityLabel} API URL configured for {$company->name}."];
        }

        $localCount = $model::where('company_id', $company->id)->count();
        $since = $localCount > 0 ? $company->{$sinceField}?->toIso8601String() : null;

        // Prefer the company's dedicated, lightweight count endpoint when configured —
        // it settles "is anything new" without ever paying for a full paginated pull.
        // Companies that haven't set one up yet fall back to `$allCountKey` off the
        // first page instead (see below).
        if (filled($company->{$countUrlField})) {
            try {
                $remoteCount = $fetchCount($company);
            } catch (ChildCrmSyncException $e) {
                return ['success' => false, 'pulled' => 0, 'message' => $e->getMessage()];
            }

            if ($remoteCount === $localCount) {
                $company->update([$lastSyncedAtField => now()]);

                return [
                    'success' => true,
                    'pulled' => 0,
                    'message' => "No new {$entityLabel} from {$company->name}.",
                ];
            }
        }

        try {
            $first = $fetchPage($company, 0, self::LIMIT, $since);
        } catch (ChildCrmSyncException $e) {
            return ['success' => false, 'pulled' => 0, 'message' => $e->getMessage()];
        }

        if (
            blank($company->{$countUrlField})
            && array_key_exists($allCountKey, $first)
            && $first[$allCountKey] === $localCount
        ) {
            $company->update([$lastSyncedAtField => now()]);

            return [
                'success' => true,
                'pulled' => 0,
                'message' => "No new {$entityLabel} from {$company->name}.",
            ];
        }

        $totalPages = $first['pages'] ?? 1;
        $pages = min($totalPages, self::MAX_PAGES);

        if ($totalPages > self::MAX_PAGES) {
            logger()->warning("{$company->name} has {$totalPages} {$entityLabel} pages, more than the ".self::MAX_PAGES.' per-run cap — this run only pulled the first '.self::MAX_PAGES.'; the rest will be picked up on a later run.');
        }

        $pulled = 0;
        $skipped = 0;

        try {
            for ($page = 0; $page < $pages; $page++) {
                $response = $page === 0 ? $first : $fetchPage($company, $page, self::LIMIT, $since);

                $items = $response['data'] ?? [];

                if ($items === []) {
                    break;
                }

                $rows = [];

                foreach ($items as $item) {
                    if (blank($item['id'] ?? null)) {
                        $skipped++;

                        continue;
                    }

                    $rows[$item['id']] = $mapItem($item);
                }

                // The child API's `since` filter is inclusive, so the last
                // already-known record from the previous run is re-fetched every
                // time — bulkUpsert() only counts genuinely new inserts, not
                // that re-touch.
                $pulled += $this->bulkUpsert($model, $company, $rows);

                // Persist the resume point after every page, not just once at the
                // end — if the job gets killed by its timeout mid-sync, the next
                // run resumes from here instead of re-pulling from scratch.
                $company->update([
                    $sinceField => $response['next_since'] ?? $company->{$sinceField},
                    $cursorField => $response['next_cursor'] ?? $company->{$cursorField},
                ]);
            }
        } catch (ChildCrmSyncException $e) {
            return ['success' => false, 'pulled' => $pulled, 'message' => $e->getMessage()];
        }

        if ($skipped > 0) {
            logger()->warning("Skipped {$skipped} {$entityLabel} record(s) from {$company->name} with no id.");
        }

        $company->update([$lastSyncedAtField => now()]);

        return [
            'success' => true,
            'pulled' => $pulled,
            'message' => trans_choice(
                '{0} No new '.$entityLabel.' from :name.|{1} Pulled :count new '.rtrim($entityLabel, 's').' from :name.|[2,*] Pulled :count new '.$entityLabel.' from :name.',
                $pulled,
                ['count' => $pulled, 'name' => $company->name],
            ),
        ];
    }

    /**
     * Builds DB-ready attributes for a bulk upsert — `meta` and `api_key` are
     * pre-serialized here since a raw upsert bypasses the model's `array`
     * cast and `encrypted` cast respectively.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function mapAffiliate(array $item): array
    {
        return [
            'name' => $item['name'] ?? null,
            'api_key' => $this->encryptApiKey($item['api_key'] ?? null),
            'is_active' => (bool) ($item['is_active'] ?? false),
            'meta' => json_encode(array_diff_key($item, array_flip(self::AFFILIATE_MAPPED_KEYS))),
            'synced_at' => now(),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function mapAdvertiser(array $item): array
    {
        return [
            'name' => $item['name'] ?? null,
            'advertiser_type' => $item['advertiser_type'] ?? null,
            'url' => $item['url'] ?? null,
            'api_key' => $this->encryptApiKey($item['api_key'] ?? null),
            'is_active' => (bool) ($item['is_active'] ?? false),
            'daily_cap' => $item['daily_cap'] ?? null,
            'hourly_cap' => $item['hourly_cap'] ?? null,
            'default_deal_type' => $item['default_deal_type'] ?? null,
            'meta' => json_encode(array_diff_key($item, array_flip(self::ADVERTISER_MAPPED_KEYS))),
            'synced_at' => now(),
        ];
    }

    /**
     * Mirrors the model's `encrypted` cast (which skips encryption for null
     * rather than encrypting an empty value) since a raw upsert bypasses it.
     */
    private function encryptApiKey(?string $apiKey): ?string
    {
        return $apiKey !== null ? Crypt::encryptString($apiKey) : null;
    }
}
