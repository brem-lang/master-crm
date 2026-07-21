<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Lead;
use App\Services\Concerns\BulkUpsertsExternalRecords;
use App\Support\ChildCrmSyncException;

class CompanyLeadsSyncer
{
    use BulkUpsertsExternalRecords;

    private const LIMIT = 100;

    /**
     * Hard cap on pages processed per sync, guarding against a child API that
     * misreports an absurd page count.
     */
    private const MAX_PAGES = 500;

    /**
     * Fields from the child CRM payload that get their own column; everything
     * else on the item is preserved in `meta`.
     */
    private const MAPPED_KEYS = [
        'id',
        'request_id',
        'firstname',
        'lastname',
        'email',
        'mobile',
        'country_code',
        'ip_address',
        'status',
        'is_ftd',
        'offer_name',
        'created_at',
    ];

    public function __construct(private readonly ChildCrmLeadsClient $client) {}

    /**
     * Pull a company's leads from its child CRM, upserting into `leads`.
     *
     * @return array{success: bool, pulled: int, message: string}
     */
    public function sync(Company $company): array
    {
        $localCount = Lead::where('company_id', $company->id)->count();

        // If there's no local data (e.g. it was deleted), the stored cursor is stale —
        // ignore it and do a full re-pull rather than only fetching what's "new" since it.
        $since = $localCount > 0 ? $company->last_synced_since?->toIso8601String() : null;

        // Prefer the company's dedicated, lightweight count endpoint when configured —
        // it settles "is anything new" without ever paying for a full get-leads call.
        // Companies that haven't set one up yet fall back to `all_leads_count` off the
        // first leads page instead (see below).
        if (filled($company->leads_count_url)) {
            try {
                $remoteCount = $this->client->fetchLeadCount($company);
            } catch (ChildCrmSyncException $e) {
                return ['success' => false, 'pulled' => 0, 'message' => $e->getMessage()];
            }

            if ($remoteCount === $localCount) {
                $company->update(['last_synced_at' => now()]);

                return [
                    'success' => true,
                    'pulled' => 0,
                    'message' => "{$company->name} is already up to date — no new leads.",
                ];
            }
        }

        try {
            $first = $this->client->fetchPage($company, 0, self::LIMIT, $since);
        } catch (ChildCrmSyncException $e) {
            return ['success' => false, 'pulled' => 0, 'message' => $e->getMessage()];
        }

        if (
            blank($company->leads_count_url)
            && array_key_exists('all_leads_count', $first)
            && $first['all_leads_count'] === $localCount
        ) {
            $company->update(['last_synced_at' => now()]);

            return [
                'success' => true,
                'pulled' => 0,
                'message' => "{$company->name} is already up to date — no new leads.",
            ];
        }

        $totalPages = $first['pages'] ?? 1;
        $pages = min($totalPages, self::MAX_PAGES);

        if ($totalPages > self::MAX_PAGES) {
            logger()->warning("{$company->name} has {$totalPages} lead pages, more than the ".self::MAX_PAGES.' per-run cap — this run only pulled the first '.self::MAX_PAGES.'; the rest will be picked up on a later run.');
        }

        $pulled = 0;
        $skipped = 0;

        try {
            for ($page = 0; $page < $pages; $page++) {
                $response = $page === 0 ? $first : $this->client->fetchPage($company, $page, self::LIMIT, $since);

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

                    $rows[$item['id']] = $this->mapLead($item);
                }

                // The child API's `since` filter is inclusive, so the last
                // already-known lead from the previous run is re-fetched every
                // time — bulkUpsert() only counts genuinely new inserts, not
                // that re-touch.
                $pulled += $this->bulkUpsert(Lead::class, $company, $rows);

                // Persist the resume point after every page, not just once at the
                // end — if the job gets killed by its timeout mid-sync, the next
                // run resumes from here instead of re-pulling from scratch.
                $company->update([
                    'last_synced_since' => $response['next_since'] ?? $company->last_synced_since,
                    'last_synced_cursor' => $response['next_cursor'] ?? $company->last_synced_cursor,
                ]);
            }
        } catch (ChildCrmSyncException $e) {
            // Partial progress from earlier pages in this run is already saved
            // (each upsert commits independently), so report what went wrong
            // rather than losing that work behind an uncaught exception.
            return ['success' => false, 'pulled' => $pulled, 'message' => $e->getMessage()];
        }

        if ($skipped > 0) {
            logger()->warning("Skipped {$skipped} lead(s) from {$company->name} with no id.");
        }

        Lead::whereNull('synced_to_parent_at')
            ->where('company_id', $company->id)
            ->update(['synced_to_parent_at' => now()]);

        $company->update(['last_synced_at' => now()]);

        return [
            'success' => true,
            'pulled' => $pulled,
            'message' => trans_choice(
                '{0} No new leads from :name.|{1} Pulled :count new lead from :name.|[2,*] Pulled :count new leads from :name.',
                $pulled,
                ['count' => $pulled, 'name' => $company->name],
            ),
        ];
    }

    /**
     * Builds DB-ready attributes for a bulk upsert — `meta` is pre-serialized
     * here since a raw upsert bypasses the model's `array` cast.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function mapLead(array $item): array
    {
        $meta = array_diff_key($item, array_flip(self::MAPPED_KEYS));

        return [
            'request_id' => $item['request_id'] ?? null,
            'first_name' => $item['firstname'] ?? null,
            'last_name' => $item['lastname'] ?? null,
            'email' => $item['email'] ?? null,
            'mobile' => $item['mobile'] ?? null,
            'country_code' => $item['country_code'] ?? null,
            'ip_address' => $item['ip_address'] ?? null,
            'status' => $item['status'] ?? null,
            'affiliate_name' => $item['affiliates']['name'] ?? null,
            'is_ftd' => (bool) ($item['is_ftd'] ?? false),
            'offer_name' => $item['offer_name'] ?? null,
            'meta' => json_encode($meta),
            'lead_created_at' => $item['created_at'] ?? null,
            'synced_at' => now(),
        ];
    }
}
