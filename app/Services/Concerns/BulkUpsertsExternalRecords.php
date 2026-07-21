<?php

namespace App\Services\Concerns;

use App\Models\Company;

/**
 * Persists a page of child-CRM records with one upsert query instead of one
 * updateOrCreate (select + write) per record — the difference between ~2
 * queries and ~200 queries for a 100-record page. Callers must pre-serialize
 * any attribute a raw upsert can't cast itself (json-encode `meta`, encrypt
 * `api_key`) before calling this.
 */
trait BulkUpsertsExternalRecords
{
    /**
     * @param  class-string  $model
     * @param  array<string, array<string, mixed>>  $rowsByExternalId  DB-ready attributes keyed by external_id
     * @return int number of rows that were newly inserted (not already present)
     */
    private function bulkUpsert(string $model, Company $company, array $rowsByExternalId): int
    {
        if ($rowsByExternalId === []) {
            return 0;
        }

        $externalIds = array_keys($rowsByExternalId);

        $existingCount = $model::where('company_id', $company->id)
            ->whereIn('external_id', $externalIds)
            ->count();

        $now = now();
        $updateColumns = [...array_keys(reset($rowsByExternalId)), 'updated_at'];

        $upsertRows = [];
        foreach ($rowsByExternalId as $externalId => $attributes) {
            $upsertRows[] = [
                'company_id' => $company->id,
                'external_id' => $externalId,
                ...$attributes,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $model::upsert($upsertRows, ['company_id', 'external_id'], $updateColumns);

        return count($externalIds) - $existingCount;
    }
}
