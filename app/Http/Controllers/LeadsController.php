<?php

namespace App\Http\Controllers;

use App\Exports\LeadsExport;
use App\Http\Controllers\Concerns\ResolvesDateRange;
use App\Models\Advertiser;
use App\Models\Affiliate;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadColumnPreference;
use App\Models\User;
use App\Services\ChildCrmDirectoryClient;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LeadsController extends Controller
{
    use ResolvesDateRange;

    /**
     * Every column key the leads table's column-visibility toggle is allowed
     * to hide — the "existing" columns are hidden alongside the "new"
     * meta-derived ones the same way, they just default to visible.
     */
    private const HIDDEN_COLUMN_KEYS = [
        'first_name', 'last_name', 'external_id', 'mobile', 'company', 'email',
        'country_code', 'sale_status', 'advertiser_name', 'is_ftd', 'affiliate_name',
        'assigned_to', 'lead_created_at',
        'request_id', 'ip_address', 'offer_name', 'status', 'ftd_released',
        'created_at', 'updated_at', 'live_lead_status',
        'country', 'city', 'locale', 'user_agent', 'platform', 'browser',
        'aff_sub', 'affiliate_id', 'advertiser_id', 'click_id', 'autologin', 'comment',
        'custom1', 'custom2', 'custom3', 'custom4', 'custom5',
        'ftd_date', 'ftd_id', 'ftd_released_at', 'ftd_released_by',
        'is_live', 'needs_review', 'is_proxy',
        'fraud_score', 'fraud_flags', 'time_to_click', 'distributed_at', 'live_lead_score',
        'click_ip', 'click_country', 'click_asn', 'click_ua',
        'submission_country', 'submission_asn', 'submission_ua',
    ];

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

        $columnPreference = LeadColumnPreference::where('user_id', $user->id)
            ->first(['hidden_columns', 'column_order']);
        $metrics['hiddenColumns'] = $columnPreference?->hidden_columns;
        $metrics['columnOrder'] = $columnPreference?->column_order;

        return Inertia::render('leads/index', $metrics);
    }

    /**
     * Persist the requesting user's hidden-column set and column order for
     * the leads table.
     */
    public function updateColumnPreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hidden_columns' => ['present', 'array'],
            'hidden_columns.*' => ['string', Rule::in(self::HIDDEN_COLUMN_KEYS)],
            'column_order' => ['sometimes', 'array'],
            'column_order.*' => ['string', Rule::in(self::HIDDEN_COLUMN_KEYS)],
        ]);

        $attributes = ['hidden_columns' => $validated['hidden_columns']];

        // Only touch column_order when the client actually sent one, so a
        // visibility-only toggle never wipes out a previously saved order.
        // Duplicates/unknown keys are dropped defensively rather than
        // rejecting the request outright — a stale client payload should
        // never be able to lock a user out of saving their preferences.
        if (isset($validated['column_order'])) {
            $attributes['column_order'] = array_values(
                array_intersect(array_unique($validated['column_order']), self::HIDDEN_COLUMN_KEYS),
            );
        }

        LeadColumnPreference::updateOrCreate(
            ['user_id' => $request->user()->id],
            $attributes,
        );

        return back();
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

    public function bulkReleaseFtd(Request $request, ChildCrmDirectoryClient $client): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->can('release-ftd'), 403);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:leads,id'],
        ]);

        // Eager-load `company` since `releaseFtd()` below hits each lead's
        // child CRM over HTTP — without it, that call would trigger a fresh
        // company query per lead.
        $leads = Lead::whereIn('id', $validated['ids'])
            ->where('is_ftd', true)
            ->where('ftd_released', false)
            ->with('company')
            ->get()
            ->filter(fn (Lead $lead) => $user->company_id === $lead->company_id || $user->can('view-all-customers'));

        $released = $leads->filter(function (Lead $lead) use ($client) {
            $result = $client->releaseFtd($lead->company, ['lead_id' => $lead->external_id]);

            if ($result['status'] < 200 || $result['status'] >= 300) {
                return false;
            }

            $lead->update(['ftd_released' => true]);

            return true;
        })->count();

        $skipped = count($validated['ids']) - $released;

        $message = trans_choice('{0} No FTDs released.|{1} :count FTD released.|[2,*] :count FTDs released.', $released, ['count' => $released]);

        if ($skipped > 0) {
            $message .= ' '.trans_choice('{1} :count skipped (already released or ineligible).|[2,*] :count skipped (already released or ineligible).', $skipped, ['count' => $skipped]);
        }

        Inertia::flash('toast', ['type' => $released > 0 ? 'success' : 'error', 'message' => $message]);

        return back();
    }

    /**
     * The company/affiliate/advertiser a "Resend" dialog for this lead may
     * route to. Non-global users are locked to the lead's own company; a
     * parent admin (`view-all-customers`) may target any active company —
     * e.g. to route a rejected lead into a different company's advertiser
     * network — and picks which one via `?company_id=`.
     */
    public function resendOptions(Request $request, Lead $lead): JsonResponse
    {
        $user = $request->user();

        $ownsCompany = $user->company_id && $user->company_id === $lead->company_id;
        $allCompanies = $user->can('view-all-customers');

        abort_unless($user->can('resend-leads') && ($ownsCompany || $allCompanies), 403);

        $companyId = $allCompanies ? ($request->integer('company_id') ?: $lead->company_id) : $lead->company_id;

        return response()->json([
            'affiliates' => Affiliate::where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'external_id', 'name']),
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
     * Re-submits an already-received lead to the child CRM's `send-lead`
     * endpoint, routed to an admin-chosen company/affiliate/advertiser. The
     * child API's own response (success, or a 400/409/422 rejection) is
     * relayed to the admin as-is — same non-throwing pattern as `sendTestLead()`.
     */
    public function resend(Request $request, Lead $lead, ChildCrmDirectoryClient $client): JsonResponse
    {
        $user = $request->user();

        $ownsCompany = $user->company_id && $user->company_id === $lead->company_id;
        $allCompanies = $user->can('view-all-customers');

        abort_unless($user->can('resend-leads') && ($ownsCompany || $allCompanies), 403);

        $request->validate([
            'company_id' => ['nullable', 'integer', Rule::exists(Company::class, 'id')->where('is_active', true)],
        ]);

        // Only a parent admin may redirect a lead to a company other than
        // its own — everyone else stays locked to the lead's own company,
        // regardless of what `company_id` they send.
        $companyId = $allCompanies ? ($request->integer('company_id') ?: $lead->company_id) : $lead->company_id;

        $company = Company::findOrFail($companyId);

        $validated = $request->validate([
            'affiliate_id' => [
                'required',
                'string',
                Rule::exists(Affiliate::class, 'external_id')->where('company_id', $companyId)->where('is_active', true),
            ],
            'advertiser_id' => [
                'required',
                'string',
                Rule::exists(Advertiser::class, 'external_id')->where('company_id', $companyId)->where('is_active', true),
            ],
        ]);

        $payload = $this->buildResendPayload($lead, $validated['affiliate_id'], $validated['advertiser_id']);

        $result = $client->resendLead($company, $payload);

        if ($result['status'] >= 200 && $result['status'] < 300) {
            $lead->update([
                'meta' => [
                    ...$lead->meta ?? [],
                    'lead_distributions' => [
                        ...$lead->distributions(),
                        [
                            'status' => 'resent',
                            'sent_at' => now()->toIso8601String(),
                            'company_id' => $company->id,
                            'affiliate_id' => $validated['affiliate_id'],
                            'advertiser_id' => $validated['advertiser_id'],
                            'triggered_by' => $user->id,
                            'request_payload' => $payload,
                            'request_url' => $company->send_lead_url,
                            'response' => $result['body'],
                        ],
                    ],
                ],
            ]);

            AuditLog::record('lead.resent', $lead, [
                'company_id' => $company->id,
                'affiliate_id' => $validated['affiliate_id'],
                'advertiser_id' => $validated['advertiser_id'],
            ]);
        }

        return response()->json($result['body'], $result['status']);
    }

    /**
     * The fields the child CRM's `send-lead` function accepts, sourced from
     * this lead's own stored data — the required contact fields live as
     * first-class Lead columns, the rest were preserved verbatim in `meta`
     * when the lead was originally synced in (see `CompanyLeadsSyncer`).
     *
     * @return array<string, mixed>
     */
    private function buildResendPayload(Lead $lead, string $affiliateId, string $advertiserId): array
    {
        $meta = $lead->meta ?? [];

        return array_filter([
            'affiliate_id' => $affiliateId,
            'advertiser_id' => $advertiserId,
            'firstname' => $lead->first_name,
            'lastname' => $lead->last_name,
            'email' => $lead->email,
            'mobile' => $lead->mobile,
            'country_code' => $lead->country_code,
            'ip_address' => $lead->ip_address,
            'offer_name' => $lead->offer_name,
            'comment' => $meta['comment'] ?? null,
            'custom1' => $meta['custom1'] ?? null,
            'custom2' => $meta['custom2'] ?? null,
            'custom3' => $meta['custom3'] ?? null,
            'aff_sub' => $meta['aff_sub'] ?? null,
            'locale' => $meta['locale'] ?? null,
            'currency' => $meta['currency'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
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
                'saleStatuses' => $scoped()->get(['meta'])
                    ->pluck('sale_status')
                    ->filter()
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
    private function baseLeadsQuery(?int $companyId, bool $rejectedOnly, ?CarbonInterface $start, ?CarbonInterface $end): Builder
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
     * @return array{search: string, status: list<string>, sale_status: list<string>, live_lead_status: string|null, country: list<string>, advertiser: string|null, affiliate: string|null, assigned_to: int|null}
     */
    private function leadFilterEcho(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'status' => array_values(array_filter((array) $request->query('status', []))),
            'sale_status' => array_values(array_filter((array) $request->query('sale_status', []))),
            'live_lead_status' => $request->query('live_lead_status') ?: null,
            'country' => array_values(array_filter((array) $request->query('country', []))),
            'advertiser' => $request->query('advertiser') ?: null,
            'affiliate' => $request->query('affiliate') ?: null,
            'assigned_to' => $request->integer('assigned_to') ?: null,
        ];
    }

    /**
     * Layers search/status/sale-status/live-lead-status/country/advertiser/
     * affiliate/assigned-to on top of an already-scoped ($scoped) query.
     * `$scoped` is a closure rather than a Builder so it can be called fresh
     * each time — once here for the meta-derived bulk-match, once for the
     * returned query — without one call's constraints bleeding into the other.
     */
    private function applyLeadFilters(\Closure $scoped, Request $request, ?int $companyId): Builder
    {
        $filters = $this->leadFilterEcho($request);
        $search = $filters['search'];
        $statuses = $filters['status'];
        $saleStatuses = $filters['sale_status'];
        $liveLeadStatus = $filters['live_lead_status'];
        $countries = $filters['country'];
        $advertiser = $filters['advertiser'];
        $affiliate = $filters['affiliate'];
        $assignedTo = $filters['assigned_to'];

        // `meta->lead_distributions`, `meta->sale_status`, and
        // `meta->live_lead_score` are JSON fields, not portable to
        // filter/aggregate in SQL across MySQL/SQLite (see
        // Lead::advertiserNames()), so the advertiser, sale-status, and
        // live-lead-status filters are resolved by pulling id+meta for the
        // scoped set once and matching all three in PHP — the same approach
        // DashboardController uses.
        $scopedLeads = ($advertiser || $saleStatuses !== [] || $liveLeadStatus)
            ? $scoped()->get(['id', 'meta'])
            : null;

        $leadIdSets = array_filter([
            $advertiser
                ? $scopedLeads->filter(fn (Lead $lead) => in_array($advertiser, $lead->advertiserNames(), true))->pluck('id')
                : null,
            $saleStatuses !== []
                ? $scopedLeads->filter(fn (Lead $lead) => in_array($lead->sale_status, $saleStatuses, true))->pluck('id')
                : null,
            $liveLeadStatus
                ? $scopedLeads->filter(fn (Lead $lead) => $lead->live_lead_status === $liveLeadStatus)->pluck('id')
                : null,
        ]);

        $matchedLeadIds = array_reduce(
            $leadIdSets,
            fn (?Collection $carry, Collection $ids) => $carry === null ? $ids : $carry->intersect($ids)->values(),
            null,
        );

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
            ->when($matchedLeadIds !== null, fn ($query) => $query->whereIn('id', $matchedLeadIds))
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
