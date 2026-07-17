<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Lead;
use App\Support\ChildCrmSyncException;

class CompanyLeadsSyncer
{
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

        try {
            $first = $this->client->fetchPage($company, 0, self::LIMIT, $since);
        } catch (ChildCrmSyncException $e) {
            return ['success' => false, 'pulled' => 0, 'message' => $e->getMessage()];
        }

        if (array_key_exists('all_leads_count', $first) && $first['all_leads_count'] === $localCount) {
            $company->update(['last_synced_at' => now()]);

            return [
                'success' => true,
                'pulled' => 0,
                'message' => "{$company->name} is already up to date — no new leads.",
            ];
        }

        $pages = min($first['pages'] ?? 1, self::MAX_PAGES);
        $pulled = 0;
        $skipped = 0;
        $lastResponse = $first;

        try {
            for ($page = 0; $page < $pages; $page++) {
                $response = $page === 0 ? $first : $this->client->fetchPage($company, $page, self::LIMIT, $since);
                $lastResponse = $response;

                $items = $response['data'] ?? [];

                if ($items === []) {
                    break;
                }

                foreach ($items as $item) {
                    if (blank($item['id'] ?? null)) {
                        $skipped++;

                        continue;
                    }

                    Lead::updateOrCreate(
                        ['company_id' => $company->id, 'external_id' => $item['id']],
                        $this->mapLead($item),
                    );
                    $pulled++;
                }
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

        $company->update([
            'last_synced_at' => now(),
            'last_synced_since' => $lastResponse['next_since'] ?? $company->last_synced_since,
            'last_synced_cursor' => $lastResponse['next_cursor'] ?? $company->last_synced_cursor,
        ]);

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
            'meta' => $meta,
            'lead_created_at' => $item['created_at'] ?? null,
            'synced_at' => now(),
        ];
    }
}
