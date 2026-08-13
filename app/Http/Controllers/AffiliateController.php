<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\AuditLog;
use App\Models\Company;
use App\Services\ChildCrmDirectoryClient;
use App\Services\CompanyDirectorySyncer;
use App\Support\ChildCrmSyncException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AffiliateController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $companyScoped = $user->company_id && $user->can('view-company-customers');
        $allCompanies = $user->can('view-all-customers');

        abort_unless($companyScoped || $allCompanies, 403);

        $companyId = $companyScoped ? $user->company_id : ($request->integer('company_id') ?: null);

        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');

        $scoped = fn () => Affiliate::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId));

        $stats = $scoped()->selectRaw('
            count(*) as total,
            sum(case when is_active = 1 then 1 else 0 end) as active,
            sum(case when is_active = 0 then 1 else 0 end) as inactive
        ')->first();

        $affiliates = $scoped()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when(! $companyId, fn ($query) => $query->with('company:id,name'))
            ->latest()
            ->paginate($this->perPage($request))
            ->withQueryString();

        // api_key is hidden by default (see Affiliate::$hidden) so it never leaks
        // through any other endpoint — deliberately revealed only here, for admins
        // who already have permission to view this page.
        $affiliates->getCollection()->makeVisible('api_key');

        $props = [
            'stats' => [
                'total' => (int) $stats->total,
                'active' => (int) $stats->active,
                'inactive' => (int) $stats->inactive,
            ],
            'affiliates' => $affiliates,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'company_id' => $companyId,
            ],
        ];

        if (! $companyScoped) {
            $props['companies'] = Company::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        }

        return Inertia::render('affiliates/index', $props);
    }

    /**
     * A small live report, not part of the regular directory sync: every
     * affiliate in the given company with IP-whitelist enforcement turned
     * on, and the IPs currently allowed for them. Parent admins must pick
     * one company at a time (rather than aggregating live calls across
     * every company on every page load).
     */
    public function whitelistedIps(Request $request, ChildCrmDirectoryClient $client): Response
    {
        $user = $request->user();

        $companyScoped = $user->company_id && $user->can('view-company-customers');
        $allCompanies = $user->can('view-all-customers');

        abort_unless($companyScoped || $allCompanies, 403);

        $companyId = $companyScoped ? $user->company_id : ($request->integer('company_id') ?: null);

        $props = [
            'affiliates' => [],
            'error' => null,
            'filters' => ['company_id' => $companyId],
        ];

        if (! $companyScoped) {
            $props['companies'] = Company::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        }

        if ($companyId) {
            $company = Company::findOrFail($companyId);

            // Hard cap on pages processed, guarding against a child API that
            // misreports an absurd page count — mirrors CompanyDirectorySyncer.
            $maxPages = 20;

            try {
                $affiliates = [];
                $page = 0;

                do {
                    $result = $client->fetchWhitelistedAffiliateIps($company, $page);
                    $affiliates = [...$affiliates, ...($result['data'] ?? [])];
                    $pages = (int) ($result['pages'] ?? 1);
                    $page++;
                } while ($page < min($pages, $maxPages));

                $props['affiliates'] = $affiliates;
            } catch (ChildCrmSyncException $e) {
                $props['error'] = $e->getMessage();
            }
        }

        return Inertia::render('affiliates/whitelisted-ips', $props);
    }

    public function updateStatus(Request $request, Affiliate $affiliate, ChildCrmDirectoryClient $client): RedirectResponse
    {
        $user = $request->user();

        $ownsCompany = $user->company_id && $user->company_id === $affiliate->company_id;

        abort_unless($user->can('update-affiliates') && ($ownsCompany || $user->can('view-all-customers')), 403);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $result = $client->updateAffiliateStatus($affiliate->company, [
            'affiliate_id' => $affiliate->external_id,
            'is_active' => $validated['is_active'],
        ]);

        if ($result['status'] < 200 || $result['status'] >= 300) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $result['body']['message'] ?? __('Failed to update the affiliate status with the child CRM.'),
            ]);

            return back();
        }

        $affiliate->update(['is_active' => $validated['is_active']]);

        AuditLog::record('affiliate.status_updated', $affiliate, ['is_active' => $validated['is_active']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Affiliate status updated.')]);

        return back();
    }

    public function bulkUpdateStatus(Request $request, ChildCrmDirectoryClient $client): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->can('update-affiliates'), 403);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:affiliates,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        // Eager-load `company` since `updateAffiliateStatus()` below hits each
        // affiliate's child CRM over HTTP — without it, that call would trigger
        // a fresh company query per affiliate.
        $affiliates = Affiliate::whereIn('id', $validated['ids'])
            ->where('is_active', ! $validated['is_active'])
            ->with('company')
            ->get()
            ->filter(fn (Affiliate $affiliate) => $user->company_id === $affiliate->company_id || $user->can('view-all-customers'));

        $updated = $affiliates->filter(function (Affiliate $affiliate) use ($client, $validated) {
            $result = $client->updateAffiliateStatus($affiliate->company, [
                'affiliate_id' => $affiliate->external_id,
                'is_active' => $validated['is_active'],
            ]);

            if ($result['status'] < 200 || $result['status'] >= 300) {
                return false;
            }

            $affiliate->update(['is_active' => $validated['is_active']]);

            AuditLog::record('affiliate.status_updated', $affiliate, ['is_active' => $validated['is_active']]);

            return true;
        })->count();

        $skipped = count($validated['ids']) - $updated;

        $message = $validated['is_active']
            ? trans_choice('{0} No affiliates activated.|{1} :count affiliate activated.|[2,*] :count affiliates activated.', $updated, ['count' => $updated])
            : trans_choice('{0} No affiliates deactivated.|{1} :count affiliate deactivated.|[2,*] :count affiliates deactivated.', $updated, ['count' => $updated]);

        if ($skipped > 0) {
            $message .= ' '.trans_choice('{1} :count skipped (already in that state or ineligible).|[2,*] :count skipped (already in that state or ineligible).', $skipped, ['count' => $skipped]);
        }

        Inertia::flash('toast', ['type' => $updated > 0 ? 'success' : 'error', 'message' => $message]);

        return back();
    }

    /**
     * Pushes an edit to the child CRM's `update-affiliate` endpoint, then
     * mirrors its response locally so this row stays byte-for-byte
     * consistent with what the child CRM now has. Empty strings for
     * nullable fields are normalized to `null` first, so clearing a field in
     * the form actually clears it. The child API's own field-level
     * validation errors (422) are re-thrown as a normal Laravel validation
     * exception; any other non-2xx reply is surfaced as a toast.
     */
    public function update(Request $request, Affiliate $affiliate, ChildCrmDirectoryClient $client): RedirectResponse
    {
        $user = $request->user();

        $ownsCompany = $user->company_id && $user->company_id === $affiliate->company_id;

        abort_unless($user->can('update-affiliates') && ($ownsCompany || $user->can('view-all-customers')), 403);

        $request->merge([
            'name' => $request->filled('name') ? $request->input('name') : null,
            'callback_url' => $request->filled('callback_url') ? $request->input('callback_url') : null,
        ]);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'callback_url' => ['nullable', 'string', 'url', 'max:255'],
            'allowed_countries' => ['nullable', 'array'],
            'allowed_countries.*' => ['string', 'size:2'],
            'allowed_ips' => ['nullable', 'array'],
            'allowed_ips.*' => ['ip'],
        ]);

        // A checkbox that's unchecked simply omits itself from the request —
        // `boolean()` correctly reads that as false, same as CompanyController.
        $validated['is_active'] = $request->boolean('is_active');
        $validated['test_mode'] = $request->boolean('test_mode');
        $validated['ip_whitelist_required'] = $request->boolean('ip_whitelist_required');

        $payload = ['id' => $affiliate->external_id, ...$validated];

        $result = $client->updateAffiliate($affiliate->company, $payload);

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
                'message' => $result['body']['message'] ?? __('Failed to update the affiliate with the child CRM.'),
            ]);

            return back();
        }

        // Prefer mirroring the child CRM's own canonical response so this row
        // stays byte-for-byte consistent with it; if it omits `data` for some
        // reason, fall back to just the fields this edit actually touched
        // rather than nulling out columns (api_key, etc.) that were never
        // part of the request.
        $responseData = $result['body']['data'] ?? null;

        $affiliate->update(
            is_array($responseData) ? CompanyDirectorySyncer::affiliateAttributes($responseData) : $validated,
        );

        AuditLog::record('affiliate.updated', $affiliate, $validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Affiliate updated.')]);

        return back();
    }

    /**
     * Applies the same edit to every selected affiliate, one
     * {@see update()}-style child-CRM call per affiliate. Only the fields
     * actually present in the request are sent — this is a genuinely
     * partial update, unlike the single-affiliate edit dialog which always
     * submits the full set — so a bulk "require IP whitelisting on these 5"
     * doesn't also have to specify a name/callback URL for all of them.
     * Affiliates the user can't touch are skipped.
     */
    public function bulkUpdate(Request $request, ChildCrmDirectoryClient $client): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->can('update-affiliates'), 403);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:affiliates,id'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'callback_url' => ['sometimes', 'nullable', 'string', 'url', 'max:255'],
            'allowed_countries' => ['sometimes', 'nullable', 'array'],
            'allowed_countries.*' => ['string', 'size:2'],
            'allowed_ips' => ['sometimes', 'nullable', 'array'],
            'allowed_ips.*' => ['ip'],
            'is_active' => ['sometimes', 'boolean'],
            'test_mode' => ['sometimes', 'boolean'],
            'ip_whitelist_required' => ['sometimes', 'boolean'],
        ]);

        $changes = collect($validated)->except('ids')->all();

        if ($changes === []) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Select at least one field to change.')]);

            return back();
        }

        // Same boolean-presence issue as `update()` — a field submitted as
        // "1"/"0" (from the bulk dialog's toggles) needs `boolean()`'s
        // parsing, not the raw validated string.
        foreach (['is_active', 'test_mode', 'ip_whitelist_required'] as $field) {
            if (array_key_exists($field, $changes)) {
                $changes[$field] = $request->boolean($field);
            }
        }

        $affiliates = Affiliate::whereIn('id', $validated['ids'])->get();

        $updated = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($affiliates as $affiliate) {
            $ownsCompany = $user->company_id && $user->company_id === $affiliate->company_id;

            if (! ($ownsCompany || $user->can('view-all-customers'))) {
                $skipped++;

                continue;
            }

            $payload = ['id' => $affiliate->external_id, ...$changes];

            $result = $client->updateAffiliate($affiliate->company, $payload);

            if ($result['status'] < 200 || $result['status'] >= 300) {
                $failed++;

                continue;
            }

            $updated++;

            $responseData = $result['body']['data'] ?? null;

            $affiliate->update(
                is_array($responseData) ? CompanyDirectorySyncer::affiliateAttributes($responseData) : $changes,
            );

            AuditLog::record('affiliate.updated', $affiliate, $changes);
        }

        $message = trans_choice('{0} No affiliates updated.|{1} :count affiliate updated.|[2,*] :count affiliates updated.', $updated, ['count' => $updated]);

        if ($failed > 0) {
            $message .= ' '.trans_choice('{1} :count failed.|[2,*] :count failed.', $failed, ['count' => $failed]);
        }

        if ($skipped > 0) {
            $message .= ' '.trans_choice('{1} :count skipped.|[2,*] :count skipped.', $skipped, ['count' => $skipped]);
        }

        Inertia::flash('toast', ['type' => $updated > 0 ? 'success' : 'error', 'message' => $message]);

        return back();
    }

    public function destroy(Request $request, Affiliate $affiliate): RedirectResponse
    {
        $user = $request->user();

        $ownsCompany = $user->company_id && $user->company_id === $affiliate->company_id;

        abort_unless($user->can('delete-affiliates') && ($ownsCompany || $user->can('view-all-customers')), 403);

        AuditLog::record('affiliate.deleted', $affiliate);

        $affiliate->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Affiliate deleted.')]);

        return back();
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->can('delete-affiliates'), 403);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:affiliates,id'],
        ]);

        $deleted = Affiliate::whereIn('id', $validated['ids'])
            ->get()
            ->filter(fn (Affiliate $affiliate) => $user->company_id === $affiliate->company_id || $user->can('view-all-customers'))
            ->each(function (Affiliate $affiliate) {
                AuditLog::record('affiliate.deleted', $affiliate);
                $affiliate->delete();
            })
            ->count();

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{0} No affiliates deleted.|{1} :count affiliate deleted.|[2,*] :count affiliates deleted.', $deleted, ['count' => $deleted])]);

        return back();
    }
}
