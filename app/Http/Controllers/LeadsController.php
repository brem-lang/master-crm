<?php

namespace App\Http\Controllers;

use App\Exports\LeadsExport;
use App\Http\Controllers\Concerns\ResolvesDateRange;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use App\Services\ChildCrmDirectoryClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LeadsController extends Controller
{
    use ResolvesDateRange;

    public function index(Request $request): Response
    {
        $user = $request->user();

        $companyScoped = $user->company_id && $user->can('view-company-customers');
        $allCompanies = $user->can('view-all-customers');

        abort_unless($companyScoped || $allCompanies, 403);

        $companyId = $companyScoped ? $user->company_id : ($request->integer('company_id') ?: null);

        $metrics = $this->leadsMetrics($request, $companyId);

        if (! $companyScoped) {
            $metrics['companies'] = Company::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        $metrics['salesReps'] = $companyScoped
            ? User::where('company_id', $companyId)->where('is_active', true)->role('sales-rep')->orderBy('name')->get(['id', 'name', 'company_id'])
            : ($allCompanies ? User::whereNotNull('company_id')->where('is_active', true)->role('sales-rep')->orderBy('name')->get(['id', 'name', 'company_id']) : []);

        return Inertia::render('leads/index', $metrics);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $user = $request->user();

        $companyScoped = $user->company_id && $user->can('view-company-customers');
        $allCompanies = $user->can('view-all-customers');

        abort_unless($companyScoped || $allCompanies, 403);

        $companyId = $companyScoped ? $user->company_id : ($request->integer('company_id') ?: null);

        [$start, $end] = $this->resolveRange($request, default: 'all');

        $scoped = fn () => $this->baseLeadsQuery($companyId, rejectedOnly: false, start: $start, end: $end);

        $query = $this->applyLeadFilters($scoped, $request, $companyId)->latest('lead_created_at');

        return Excel::download(new LeadsExport($query), 'leads-'.now()->format('Y-m-d-His').'.xlsx');
    }

    public function rejected(Request $request): Response
    {
        $user = $request->user();

        $companyScoped = $user->company_id && $user->can('view-company-customers');
        $allCompanies = $user->can('view-all-customers');

        abort_unless($companyScoped || $allCompanies, 403);

        $companyId = $companyScoped ? $user->company_id : ($request->integer('company_id') ?: null);

        $metrics = $this->leadsMetrics($request, $companyId, rejectedOnly: true);

        if (! $companyScoped) {
            $metrics['companies'] = Company::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        $metrics['salesReps'] = $companyScoped
            ? User::where('company_id', $companyId)->where('is_active', true)->role('sales-rep')->orderBy('name')->get(['id', 'name', 'company_id'])
            : ($allCompanies ? User::whereNotNull('company_id')->where('is_active', true)->role('sales-rep')->orderBy('name')->get(['id', 'name', 'company_id']) : []);

        // Visiting this page is the "seen" event for the sidebar badge — not
        // user-editable input, so this is stamped directly rather than via
        // mass assignment.
        $user->forceFill(['rejected_leads_viewed_at' => now()])->save();

        return Inertia::render('leads/rejected', $metrics);
    }

    public function conversions(Request $request): Response
    {
        $user = $request->user();

        $companyScoped = $user->company_id && $user->can('view-company-customers');
        $allCompanies = $user->can('view-all-customers');

        abort_unless($companyScoped || $allCompanies, 403);

        $companyId = $companyScoped ? $user->company_id : ($request->integer('company_id') ?: null);

        $metrics = $this->conversionsMetrics($request, $companyId);

        if (! $companyScoped) {
            $metrics['companies'] = Company::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        $metrics['salesReps'] = $companyScoped
            ? User::where('company_id', $companyId)->where('is_active', true)->role('sales-rep')->orderBy('name')->get(['id', 'name', 'company_id'])
            : ($allCompanies ? User::whereNotNull('company_id')->where('is_active', true)->role('sales-rep')->orderBy('name')->get(['id', 'name', 'company_id']) : []);

        return Inertia::render('leads/conversions', $metrics);
    }

    public function releaseFtd(Request $request, Lead $lead, ChildCrmDirectoryClient $client): RedirectResponse
    {
        $user = $request->user();

        $ownsCompany = $user->company_id && $user->company_id === $lead->company_id;

        abort_unless($user->can('release-ftd') && ($ownsCompany || $user->can('view-all-customers')), 403);
        abort_unless($lead->is_ftd, 422);

        $result = $client->releaseFtd($lead->company, ['lead_id' => $lead->external_id]);

        if ($result['status'] < 200 || $result['status'] >= 300) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $result['body']['message'] ?? __('Failed to release the FTD with the child CRM.'),
            ]);

            return back();
        }

        $lead->update(['ftd_released' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('FTD marked as released.')]);

        return back();
    }

    public function assign(Request $request, Lead $lead): RedirectResponse
    {
        $user = $request->user();

        $ownsCompany = $user->company_id && $user->company_id === $lead->company_id;

        abort_unless($user->can('assign-leads') && ($ownsCompany || $user->can('view-all-customers')), 403);

        $validated = $request->validate([
            'assigned_to' => [
                'nullable',
                'integer',
                Rule::exists(User::class, 'id')->where('company_id', $lead->company_id)->where('is_active', true),
            ],
        ]);

        if ($validated['assigned_to'] ?? null) {
            abort_unless(
                User::find($validated['assigned_to'])->hasRole('sales-rep'),
                422,
            );
        }

        $lead->update(['assigned_to' => $validated['assigned_to'] ?? null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lead assignment updated.')]);

        return back();
    }

    public function bulkAssign(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->can('assign-leads'), 403);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:leads,id'],
            'assigned_to' => ['nullable', 'integer', Rule::exists(User::class, 'id')->where('is_active', true)],
        ]);

        $assignedTo = $validated['assigned_to'] ?? null;
        $repCompanyId = null;

        if ($assignedTo) {
            $rep = User::find($assignedTo);
            abort_unless($rep->hasRole('sales-rep'), 422);
            $repCompanyId = $rep->company_id;
        }

        $leads = Lead::whereIn('id', $validated['ids'])->get();

        $eligible = $leads->filter(function (Lead $lead) use ($user, $assignedTo, $repCompanyId) {
            $ownsCompany = $user->company_id && $user->company_id === $lead->company_id;
            $canTouch = $ownsCompany || $user->can('view-all-customers');

            return $canTouch && (! $assignedTo || $lead->company_id === $repCompanyId);
        });

        Lead::whereIn('id', $eligible->pluck('id'))->update(['assigned_to' => $assignedTo]);

        $updated = $eligible->count();
        $skipped = $leads->count() - $updated;

        $message = trans_choice('{0} No leads assigned.|{1} :count lead assigned.|[2,*] :count leads assigned.', $updated, ['count' => $updated]);

        if ($skipped > 0) {
            $message .= ' '.trans_choice('{1} :count skipped (different company).|[2,*] :count skipped (different company).', $skipped, ['count' => $skipped]);
        }

        Inertia::flash('toast', ['type' => $updated > 0 ? 'success' : 'error', 'message' => $message]);

        return back();
    }

    public function destroy(Request $request, Lead $lead): RedirectResponse
    {
        $user = $request->user();

        $ownsCompany = $user->company_id && $user->company_id === $lead->company_id;

        abort_unless($user->can('delete-leads') && ($ownsCompany || $user->can('view-all-customers')), 403);

        AuditLog::record('lead.deleted', $lead);

        $lead->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lead deleted.')]);

        return back();
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->can('delete-leads'), 403);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:leads,id'],
        ]);

        $deleted = Lead::whereIn('id', $validated['ids'])
            ->get()
            ->filter(fn (Lead $lead) => $user->company_id === $lead->company_id || $user->can('view-all-customers'))
            ->each(function (Lead $lead) {
                AuditLog::record('lead.deleted', $lead);
                $lead->delete();
            })
            ->count();

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{0} No leads deleted.|{1} :count lead deleted.|[2,*] :count leads deleted.', $deleted, ['count' => $deleted])]);

        return back();
    }

    public function destroyRejected(Request $request, Lead $lead): RedirectResponse
    {
        $user = $request->user();

        $ownsCompany = $user->company_id && $user->company_id === $lead->company_id;

        abort_unless($user->can('delete-rejected-leads') && ($ownsCompany || $user->can('view-all-customers')), 403);
        abort_unless($lead->status === 'rejected', 422);

        AuditLog::record('lead.deleted', $lead);

        $lead->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lead deleted.')]);

        return back();
    }

    public function bulkDestroyRejected(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->can('delete-rejected-leads'), 403);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:leads,id'],
        ]);

        $deleted = Lead::whereIn('id', $validated['ids'])
            ->where('status', 'rejected')
            ->get()
            ->filter(fn (Lead $lead) => $user->company_id === $lead->company_id || $user->can('view-all-customers'))
            ->each(function (Lead $lead) {
                AuditLog::record('lead.deleted', $lead);
                $lead->delete();
            })
            ->count();

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{0} No leads deleted.|{1} :count lead deleted.|[2,*] :count leads deleted.', $deleted, ['count' => $deleted])]);

        return back();
    }

    /**
     * Build lead metrics/list for a single company, or across all companies when
     * `$companyId` is null (parent admin view). `$rejectedOnly` toggles between
     * the main Leads page (everything except rejected) and the Rejected Leads
     * page (only rejected) — `status` is nullable free text, so "not rejected"
     * must stay null-safe rather than relying on `status != 'rejected'` alone.
     *
     * @return array<string, mixed>
     */
    private function leadsMetrics(Request $request, ?int $companyId, bool $rejectedOnly = false): array
    {
        $viewLeadId = $request->integer('view_lead') ?: null;

        [$start, $end, $rangeMeta] = $this->resolveRange($request, default: 'all');

        $scoped = fn () => $this->baseLeadsQuery($companyId, $rejectedOnly, $start, $end);

        $stats = $scoped()
            ->selectRaw('
                count(*) as total,
                sum(case when is_ftd = 1 then 1 else 0 end) as ftd
            ')
            ->first();

        return [
            'total' => (int) $stats->total,
            'ftd' => (int) $stats->ftd,
            'byStatus' => $scoped()
                ->select('status')
                ->selectRaw('count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'leads' => $this->applyLeadFilters($scoped, $request, $companyId)
                ->latest('lead_created_at')
                ->paginate($this->perPage($request))
                ->withQueryString(),
            'viewLead' => $viewLeadId ? Lead::with(['company:id,name', 'assignee:id,name'])->find($viewLeadId) : null,
            'filterOptions' => [
                'countries' => $scoped()
                    ->whereNotNull('country_code')
                    ->where('country_code', '!=', '')
                    ->distinct()
                    ->orderBy('country_code')
                    ->pluck('country_code'),
                'affiliates' => $scoped()
                    ->whereNotNull('affiliate_name')
                    ->where('affiliate_name', '!=', '')
                    ->distinct()
                    ->orderBy('affiliate_name')
                    ->pluck('affiliate_name'),
                'advertisers' => $scoped()->get(['meta'])
                    ->flatMap(fn (Lead $lead) => $lead->advertiserNames())
                    ->unique()
                    ->sort()
                    ->values(),
            ],
            'filters' => [
                ...$this->leadFilterEcho($request),
                'company_id' => $companyId,
                'range' => $rangeMeta['range'],
                'from' => $rangeMeta['from'],
                'to' => $rangeMeta['to'],
            ],
        ];
    }

    /**
     * The base scope shared by every leads list/export: company + rejected-vs-not
     * + date range. Search/status/country/advertiser/affiliate/assigned-to are
     * layered on top by `applyLeadFilters()` — kept separate so callers (e.g. the
     * `filterOptions` dropdown lists) can query the unfiltered baseline.
     */
    private function baseLeadsQuery(?int $companyId, bool $rejectedOnly, ?Carbon $start, ?Carbon $end): Builder
    {
        return Lead::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->when(
                $rejectedOnly,
                fn ($query) => $query->where('status', 'rejected'),
                fn ($query) => $query->where(fn ($query) => $query->where('status', '!=', 'rejected')->orWhereNull('status')),
            )
            ->when($start, fn ($query) => $query->where('lead_created_at', '>=', $start))
            ->when($end, fn ($query) => $query->where('lead_created_at', '<=', $end));
    }

    /**
     * @return array{search: string, status: list<string>, country: list<string>, advertiser: string|null, affiliate: string|null, assigned_to: int|null}
     */
    private function leadFilterEcho(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'status' => array_values(array_filter((array) $request->query('status', []))),
            'country' => array_values(array_filter((array) $request->query('country', []))),
            'advertiser' => $request->query('advertiser') ?: null,
            'affiliate' => $request->query('affiliate') ?: null,
            'assigned_to' => $request->integer('assigned_to') ?: null,
        ];
    }

    /**
     * Layers search/status/country/advertiser/affiliate/assigned-to on top of an
     * already-scoped ($scoped) query. `$scoped` is a closure rather than a Builder
     * so it can be called fresh each time — once here for the advertiser bulk-match,
     * once for the returned query — without one call's constraints bleeding into
     * the other.
     */
    private function applyLeadFilters(\Closure $scoped, Request $request, ?int $companyId): Builder
    {
        $filters = $this->leadFilterEcho($request);
        $search = $filters['search'];
        $statuses = $filters['status'];
        $countries = $filters['country'];
        $advertiser = $filters['advertiser'];
        $affiliate = $filters['affiliate'];
        $assignedTo = $filters['assigned_to'];

        // `meta->lead_distributions` is a JSON array, not portable to filter/aggregate
        // in SQL across MySQL/SQLite (see Lead::advertiserNames()), so the advertiser
        // filter is resolved by pulling id+meta for the scoped set and matching in
        // PHP — the same approach DashboardController uses.
        $advertiserLeadIds = $advertiser
            ? $scoped()->get(['id', 'meta'])
                ->filter(fn (Lead $lead) => in_array($advertiser, $lead->advertiserNames(), true))
                ->pluck('id')
            : null;

        return $scoped()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('request_id', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            }))
            ->when($statuses !== [], fn ($query) => $query->whereIn('status', $statuses))
            ->when($countries !== [], fn ($query) => $query->whereIn('country_code', $countries))
            ->when($affiliate, fn ($query) => $query->where('affiliate_name', $affiliate))
            ->when($advertiserLeadIds !== null, fn ($query) => $query->whereIn('id', $advertiserLeadIds))
            ->when($assignedTo, fn ($query) => $query->where('assigned_to', $assignedTo))
            ->when(! $companyId, fn ($query) => $query->with('company:id,name'))
            ->with('assignee:id,name');
    }

    /**
     * Build FTD conversion metrics/list for a single company, or across all
     * companies when `$companyId` is null (parent admin view). Every row is
     * already `is_ftd = true`; `released` further narrows to whether
     * `ftd_released` has been flipped on. Reuses `applyLeadFilters()` for the
     * search/status/country/advertiser/affiliate/assigned-to filters shared
     * with the Leads/Rejected pages, layering `released` on top.
     *
     * @return array<string, mixed>
     */
    private function conversionsMetrics(Request $request, ?int $companyId): array
    {
        $released = $request->query('released');
        $viewLeadId = $request->integer('view_lead') ?: null;

        [$start, $end, $rangeMeta] = $this->resolveRange($request, default: 'all');

        $scoped = fn () => Lead::query()
            ->where('is_ftd', true)
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->when($start, fn ($query) => $query->where('lead_created_at', '>=', $start))
            ->when($end, fn ($query) => $query->where('lead_created_at', '<=', $end));

        $stats = $scoped()
            ->selectRaw('
                count(*) as total,
                sum(case when ftd_released = 1 then 1 else 0 end) as released
            ')
            ->first();

        return [
            'total' => (int) $stats->total,
            'released' => (int) $stats->released,
            'pending' => (int) $stats->total - (int) $stats->released,
            'byStatus' => $scoped()
                ->select('status')
                ->selectRaw('count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'leads' => $this->applyLeadFilters($scoped, $request, $companyId)
                ->when($released === 'released', fn ($query) => $query->where('ftd_released', true))
                ->when($released === 'pending', fn ($query) => $query->where('ftd_released', false))
                ->orderBy('ftd_released')
                ->latest('lead_created_at')
                ->paginate($this->perPage($request))
                ->withQueryString(),
            'viewLead' => $viewLeadId ? Lead::with(['company:id,name', 'assignee:id,name'])->find($viewLeadId) : null,
            'filterOptions' => [
                'countries' => $scoped()
                    ->whereNotNull('country_code')
                    ->where('country_code', '!=', '')
                    ->distinct()
                    ->orderBy('country_code')
                    ->pluck('country_code'),
                'affiliates' => $scoped()
                    ->whereNotNull('affiliate_name')
                    ->where('affiliate_name', '!=', '')
                    ->distinct()
                    ->orderBy('affiliate_name')
                    ->pluck('affiliate_name'),
                'advertisers' => $scoped()->get(['meta'])
                    ->flatMap(fn (Lead $lead) => $lead->advertiserNames())
                    ->unique()
                    ->sort()
                    ->values(),
            ],
            'filters' => [
                ...$this->leadFilterEcho($request),
                'released' => $released,
                'company_id' => $companyId,
                'range' => $rangeMeta['range'],
                'from' => $rangeMeta['from'],
                'to' => $rangeMeta['to'],
            ],
        ];
    }
}
