<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use App\Services\ChildCrmDirectoryClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeadsController extends Controller
{
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
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');
        $assignedTo = $request->integer('assigned_to') ?: null;
        $viewLeadId = $request->integer('view_lead') ?: null;

        $scoped = fn () => Lead::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->when(
                $rejectedOnly,
                fn ($query) => $query->where('status', 'rejected'),
                fn ($query) => $query->where(fn ($query) => $query->where('status', '!=', 'rejected')->orWhereNull('status')),
            );

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
            'leads' => $scoped()
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                }))
                ->when($status, fn ($query) => $query->where('status', $status))
                ->when($assignedTo, fn ($query) => $query->where('assigned_to', $assignedTo))
                ->when(! $companyId, fn ($query) => $query->with('company:id,name'))
                ->with('assignee:id,name')
                ->latest('lead_created_at')
                ->paginate($this->perPage($request))
                ->withQueryString(),
            'viewLead' => $viewLeadId ? Lead::with(['company:id,name', 'assignee:id,name'])->find($viewLeadId) : null,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'company_id' => $companyId,
                'assigned_to' => $assignedTo,
            ],
        ];
    }

    /**
     * Build FTD conversion metrics/list for a single company, or across all
     * companies when `$companyId` is null (parent admin view). Every row is
     * already `is_ftd = true`; `released` further narrows to whether
     * `ftd_released` has been flipped on.
     *
     * @return array<string, mixed>
     */
    private function conversionsMetrics(Request $request, ?int $companyId): array
    {
        $search = trim((string) $request->query('search', ''));
        $released = $request->query('released');
        $assignedTo = $request->integer('assigned_to') ?: null;
        $viewLeadId = $request->integer('view_lead') ?: null;

        $scoped = fn () => Lead::query()
            ->where('is_ftd', true)
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId));

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
            'leads' => $scoped()
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                }))
                ->when($released === 'released', fn ($query) => $query->where('ftd_released', true))
                ->when($released === 'pending', fn ($query) => $query->where('ftd_released', false))
                ->when($assignedTo, fn ($query) => $query->where('assigned_to', $assignedTo))
                ->when(! $companyId, fn ($query) => $query->with('company:id,name'))
                ->with('assignee:id,name')
                ->orderBy('ftd_released')
                ->latest('lead_created_at')
                ->paginate($this->perPage($request))
                ->withQueryString(),
            'viewLead' => $viewLeadId ? Lead::with(['company:id,name', 'assignee:id,name'])->find($viewLeadId) : null,
            'filters' => [
                'search' => $search,
                'released' => $released,
                'company_id' => $companyId,
                'assigned_to' => $assignedTo,
            ],
        ];
    }
}
