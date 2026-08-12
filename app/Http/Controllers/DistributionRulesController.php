<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\DistributionRule;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DistributionRulesController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $companyScoped = $user->company_id && $user->can('view-company-customers');
        $allCompanies = $user->can('view-all-customers');

        abort_unless($companyScoped || $allCompanies, 403);

        $companyId = $companyScoped ? $user->company_id : ($request->integer('company_id') ?: null);

        $status = $request->query('status');
        $priorityType = $request->query('priority_type');
        $countryCode = $request->query('country_code');

        $scoped = fn () => DistributionRule::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId));

        $stats = $scoped()->selectRaw('
            count(*) as total,
            sum(case when is_active = 1 then 1 else 0 end) as active,
            sum(case when is_active = 0 then 1 else 0 end) as inactive
        ')->first();

        $rules = $scoped()
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($priorityType, fn ($query) => $query->where('priority_type', $priorityType))
            ->when($countryCode, fn ($query) => $query->where('country_code', $countryCode))
            ->when(! $companyId, fn ($query) => $query->with('company:id,name'))
            ->latest()
            ->paginate($this->perPage($request))
            ->withQueryString();

        $this->attachLeadsCounts($rules->getCollection());

        $props = [
            'stats' => [
                'total' => (int) $stats->total,
                'active' => (int) $stats->active,
                'inactive' => (int) $stats->inactive,
            ],
            'rules' => $rules,
            'filters' => [
                'status' => $status,
                'priority_type' => $priorityType,
                'country_code' => $countryCode,
                'company_id' => $companyId,
            ],
        ];

        if (! $companyScoped) {
            $props['companies'] = Company::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        }

        return Inertia::render('distribution-rules/index', $props);
    }

    /**
     * A best-effort count of leads each rule applies to — there's no real
     * foreign key between leads and distribution rules, so this matches on
     * the same signals a child CRM's router would use: same company, not
     * rejected, matching country, the lead's `meta->affiliate_id` equal to
     * the rule's affiliate, and a *successful* (`status: "sent"`) entry in
     * the lead's `meta->lead_distributions` history whose advertiser_id
     * equals the rule's. A null rule attribute matches anything for that
     * dimension, same as the distribution engine would treat an
     * unrestricted rule.
     *
     * Checking "does this JSON array contain an object where status=sent AND
     * advertiser_id=X" isn't expressible portably across MySQL/SQLite in
     * pure SQL, and a plain substring search isn't safe either — a lead can
     * carry a *failed* attempt to this advertiser that later fell through to
     * a different one, and a naive LIKE match would still count it. So the
     * cheap dimensions (company/country/affiliate/status) are filtered in
     * SQL, and only the advertiser+outcome check happens in PHP against that
     * already-narrow candidate set.
     *
     * Rules sharing the same company/country/affiliate are grouped so their
     * shared candidate set is only fetched once — one query per distinct
     * group on the page, not one per rule.
     *
     * @param  Collection<int, DistributionRule>  $rules
     */
    private function attachLeadsCounts(Collection $rules): void
    {
        $rules
            ->groupBy(fn (DistributionRule $rule) => implode('|', [
                $rule->company_id, $rule->country_code, $rule->affiliate_id,
            ]))
            ->each(function (Collection $group) {
                $metas = $this->candidateLeadMetas($group->first());

                $group->each(function (DistributionRule $rule) use ($metas) {
                    $rule->leads_count = $this->countLeadsForAdvertiser($metas, $rule->advertiser_id);
                });
            });
    }

    /**
     * @return Collection<int, array<string, mixed>|null>
     */
    private function candidateLeadMetas(DistributionRule $rule): Collection
    {
        return Lead::query()
            ->where('company_id', $rule->company_id)
            // `status` is nullable, and SQL's `!=` treats NULL as neither equal
            // nor unequal to 'rejected' — the explicit orWhereNull keeps
            // status-less leads counted instead of silently dropping them too.
            ->where(fn ($query) => $query->where('status', '!=', 'rejected')
                ->orWhereNull('status'))
            ->when($rule->country_code, fn ($query) => $query->where('country_code', $rule->country_code))
            ->when($rule->affiliate_id, fn ($query) => $query->where('meta->affiliate_id', $rule->affiliate_id))
            ->pluck('meta');
    }

    /**
     * @param  Collection<int, array<string, mixed>|null>  $metas
     */
    private function countLeadsForAdvertiser(Collection $metas, ?string $advertiserId): int
    {
        if ($advertiserId === null) {
            return $metas->count();
        }

        return $metas->filter(function (?array $meta) use ($advertiserId) {
            $distributions = $meta['lead_distributions'] ?? [];

            return collect($distributions)->contains(
                fn ($distribution) => ($distribution['status'] ?? null) === 'sent'
                    && ($distribution['advertiser_id'] ?? null) === $advertiserId,
            );
        })->count();
    }
}
