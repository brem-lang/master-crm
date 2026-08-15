<?php

namespace App\Http\Controllers;

use App\Models\Advertiser;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\DistributionRule;
use App\Models\Lead;
use App\Services\ChildCrmDirectoryClient;
use App\Services\CompanyDirectorySyncer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
     * The advertisers a bulk edit dialog may preview — not tied to one
     * rule, since the selection can span companies. `company_id` only
     * narrows this preview; the actual bulk update below re-validates
     * `advertiser_id` per rule against its own company regardless of what's
     * chosen here.
     */
    public function bulkEditOptions(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->can('update-distribution-rules'), 403);

        $allCompanies = $user->can('view-all-customers');

        abort_if(! $allCompanies && ! $user->company_id, 403);

        $companyId = $allCompanies ? ($request->integer('company_id') ?: $user->company_id) : $user->company_id;

        return response()->json([
            'advertisers' => Advertiser::where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'external_id', 'name']),
            'companies' => $allCompanies
                ? Company::where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : null,
        ]);
    }

    /**
     * Applies the same edit to every selected rule, one
     * {@see update()}-style child-CRM call per rule. Only the fields
     * actually present in the request are sent — this is a genuinely
     * partial update, unlike the single-rule edit dialog which always
     * submits the full set — so a bulk "deactivate these 5 rules" doesn't
     * also have to specify an advertiser/priority/weight for all of them.
     * `advertiser_id` validity is checked per rule against its own company,
     * since the selection can span companies. Rules the user can't touch,
     * or for which the chosen advertiser isn't valid, are skipped.
     */
    public function bulkUpdate(Request $request, ChildCrmDirectoryClient $client): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->can('update-distribution-rules'), 403);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:distribution_rules,id'],
            'advertiser_id' => ['sometimes', 'string'],
            'country_code' => ['sometimes', 'nullable', 'string', 'size:2'],
            'priority' => ['sometimes', 'integer', 'min:0'],
            'weight' => ['sometimes', 'integer', 'min:0'],
            'priority_type' => ['sometimes', Rule::in(['primary', 'fallback'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $changes = collect($validated)->except('ids')->all();

        if ($changes === []) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Select at least one field to change.')]);

            return back();
        }

        // Same string-vs-number issue as `update()` — cast before these are
        // JSON-encoded, or the child CRM's type-aware validation rejects a
        // quoted number/boolean.
        if (array_key_exists('priority', $changes)) {
            $changes['priority'] = (int) $changes['priority'];
        }

        if (array_key_exists('weight', $changes)) {
            $changes['weight'] = (int) $changes['weight'];
        }

        if (array_key_exists('is_active', $changes)) {
            $changes['is_active'] = $request->boolean('is_active');
        }

        $rules = DistributionRule::whereIn('id', $validated['ids'])->get();

        $updated = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($rules as $rule) {
            $ownsCompany = $user->company_id && $user->company_id === $rule->company_id;

            $advertiserValid = ! array_key_exists('advertiser_id', $changes) || Advertiser::where('company_id', $rule->company_id)
                ->where('external_id', $changes['advertiser_id'])
                ->where('is_active', true)
                ->exists();

            if (! ($ownsCompany || $user->can('view-all-customers')) || ! $advertiserValid) {
                $skipped++;

                continue;
            }

            $payload = ['id' => $rule->external_id, ...$changes];

            $result = $client->updateDistributionRule($rule->company, $payload);

            if ($result['status'] < 200 || $result['status'] >= 300) {
                $failed++;

                continue;
            }

            $updated++;

            $responseData = $result['body']['data'] ?? null;

            $rule->update(
                is_array($responseData) ? CompanyDirectorySyncer::distributionRuleAttributes($responseData) : $changes,
            );

            AuditLog::record('distribution_rule.updated', $rule, $changes);
        }

        $message = trans_choice('{0} No distribution rules updated.|{1} :count distribution rule updated.|[2,*] :count distribution rules updated.', $updated, ['count' => $updated]);

        if ($failed > 0) {
            $message .= ' '.trans_choice('{1} :count failed.|[2,*] :count failed.', $failed, ['count' => $failed]);
        }

        if ($skipped > 0) {
            $message .= ' '.trans_choice('{1} :count skipped.|[2,*] :count skipped.', $skipped, ['count' => $skipped]);
        }

        Inertia::flash('toast', ['type' => $updated > 0 ? 'success' : 'error', 'message' => $message]);

        return back();
    }

    /**
     * The advertisers an edit dialog for this rule may reassign it to —
     * always scoped to the rule's own company, since a distribution rule
     * only exists in its own child CRM.
     */
    public function editOptions(Request $request, DistributionRule $distributionRule): JsonResponse
    {
        $user = $request->user();

        $ownsCompany = $user->company_id && $user->company_id === $distributionRule->company_id;

        abort_unless($user->can('update-distribution-rules') && ($ownsCompany || $user->can('view-all-customers')), 403);

        return response()->json([
            'advertisers' => Advertiser::where('company_id', $distributionRule->company_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'external_id', 'name']),
        ]);
    }

    /**
     * The per-country breakdown behind the "Leads" count shown in the view
     * modal — same matching signals as {@see attachLeadsCounts()} (company,
     * not rejected, affiliate, advertiser+sent), except `country_code` is
     * never filtered on: it's the dimension being grouped by instead, so a
     * rule with no country restriction shows where its leads actually came
     * from rather than a single opaque total.
     */
    public function leadsByCountry(Request $request, DistributionRule $distributionRule): JsonResponse
    {
        $user = $request->user();

        $ownsCompany = $user->company_id && $user->company_id === $distributionRule->company_id;

        abort_unless($ownsCompany || $user->can('view-all-customers'), 403);

        $leads = Lead::query()
            ->where('company_id', $distributionRule->company_id)
            ->where(fn ($query) => $query->where('status', '!=', 'rejected')
                ->orWhereNull('status'))
            ->when($distributionRule->affiliate_id, fn ($query) => $query->where('meta->affiliate_id', $distributionRule->affiliate_id))
            ->get(['country_code', 'meta']);

        $advertiserId = $distributionRule->advertiser_id;

        $counts = $leads
            ->when($advertiserId !== null, fn ($leads) => $leads->filter(function (Lead $lead) use ($advertiserId) {
                $distributions = $lead->meta['lead_distributions'] ?? [];

                return collect($distributions)->contains(
                    fn ($distribution) => ($distribution['status'] ?? null) === 'sent'
                        && ($distribution['advertiser_id'] ?? null) === $advertiserId,
                );
            }))
            ->groupBy(fn (Lead $lead) => $lead->country_code ?? '—')
            ->map->count()
            ->sortDesc();

        return response()->json([
            'counts' => $counts->map(fn ($count, $countryCode) => [
                'country_code' => $countryCode,
                'count' => $count,
            ])->values(),
        ]);
    }

    /**
     * Pushes an edit to the child CRM's `update-distribution-rule` endpoint,
     * then mirrors its response locally so this row stays byte-for-byte
     * consistent with what the child CRM now has. The child API's own
     * field-level validation errors (422) are re-thrown as a normal Laravel
     * validation exception so the edit dialog can show them per field; any
     * other non-2xx reply (404/400/409/401/502) is surfaced as a toast.
     */
    public function update(Request $request, DistributionRule $distributionRule, ChildCrmDirectoryClient $client): RedirectResponse
    {
        $user = $request->user();

        $ownsCompany = $user->company_id && $user->company_id === $distributionRule->company_id;

        abort_unless($user->can('update-distribution-rules') && ($ownsCompany || $user->can('view-all-customers')), 403);

        $validated = $request->validate([
            'advertiser_id' => [
                'required',
                'string',
                Rule::exists(Advertiser::class, 'external_id')->where('company_id', $distributionRule->company_id)->where('is_active', true),
            ],
            'country_code' => ['nullable', 'string', 'size:2'],
            'priority' => ['required', 'integer', 'min:0'],
            'weight' => ['required', 'integer', 'min:0'],
            'priority_type' => ['required', Rule::in(['primary', 'fallback'])],
        ]);

        // A checkbox that's unchecked simply omits itself from the request —
        // `boolean()` correctly reads that as false, same as CompanyController.
        $validated['is_active'] = $request->boolean('is_active');

        // The `integer` validation rule above only checks that these look
        // like integers — it doesn't cast them, so they're still the raw
        // strings the HTML form submitted (e.g. "10"). Cast explicitly
        // before they're JSON-encoded, or the child CRM's own stricter,
        // type-aware validation rejects a quoted number.
        $validated['priority'] = (int) $validated['priority'];
        $validated['weight'] = (int) $validated['weight'];

        $payload = ['id' => $distributionRule->external_id, ...$validated];

        $result = $client->updateDistributionRule($distributionRule->company, $payload);

        if ($result['status'] === 422 && is_array($result['body']['errors'] ?? null)) {
            throw ValidationException::withMessages(
                collect($result['body']['errors'])
                    ->mapWithKeys(fn ($message, $field) => [$field => [is_string($message) ? $message : __('Invalid value.')]])
                    ->all(),
            );
        }

        if ($result['status'] < 200 || $result['status'] >= 300) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $result['body']['message'] ?? __('Failed to update the distribution rule with the child CRM.'),
            ]);

            return back();
        }

        // Prefer mirroring the child CRM's own canonical response so this row
        // stays byte-for-byte consistent with it; if it omits `data` for some
        // reason, fall back to just the fields this edit actually touched
        // rather than nulling out columns (daily_cap, timezone, etc.) that
        // were never part of the request.
        $responseData = $result['body']['data'] ?? null;

        $distributionRule->update(
            is_array($responseData) ? CompanyDirectorySyncer::distributionRuleAttributes($responseData) : $validated,
        );

        AuditLog::record('distribution_rule.updated', $distributionRule, $validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Distribution rule updated.')]);

        return back();
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
