<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\AuditLog;
use App\Models\Company;
use App\Services\ChildCrmDirectoryClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
