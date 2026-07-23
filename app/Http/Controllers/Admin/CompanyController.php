<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompanyStoreRequest;
use App\Http\Requests\Admin\CompanyUpdateRequest;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\JobRun;
use App\Models\User;
use App\Services\CompanyDirectorySyncer;
use App\Services\CompanyLeadsSyncer;
use App\Support\CompanyHealth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(Request $request, CompanyHealth $companyHealth): Response
    {
        $this->authorize('viewAny', Company::class);

        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');
        $viewCompanyId = $request->integer('view_company') ?: null;

        $stats = Company::query()->selectRaw('
            count(*) as total,
            sum(case when is_active = 1 then 1 else 0 end) as active,
            sum(case when is_active = 0 then 1 else 0 end) as inactive
        ')->first();

        $companies = Company::withCount('users')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            }))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->latest()
            ->paginate($this->perPage($request))
            ->withQueryString();

        $companyHealth->attach($companies->getCollection());

        return Inertia::render('admin/companies/index', [
            'stats' => [
                'total' => (int) $stats->total,
                'active' => (int) $stats->active,
                'inactive' => (int) $stats->inactive,
            ],
            'viewCompany' => $viewCompanyId ? Company::find($viewCompanyId) : null,
            'companyUsers' => $viewCompanyId
                ? User::where('company_id', $viewCompanyId)->with('roles')->orderBy('name')->get()
                : null,
            'companies' => $companies,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function store(CompanyStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Company::class);

        $company = Company::create([
            ...$request->validated(),
            'slug' => Company::generateUniqueSlug($request->validated('name')),
            'is_active' => $request->boolean('is_active'),
        ]);

        AuditLog::record('company.created', $company);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company created.')]);

        return to_route('companies.index');
    }

    public function update(CompanyUpdateRequest $request, Company $company): RedirectResponse
    {
        $this->authorize('update', $company);

        $data = $request->validated();

        // Blank means "keep the current key" — the field is never prefilled
        // in the edit form since api_key is never sent to the frontend.
        if (blank($data['api_key'] ?? null)) {
            unset($data['api_key']);
        }

        $company->update([
            ...$data,
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->recordCompanyUpdate($company);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company updated.')]);

        return to_route('companies.index');
    }

    public function destroy(Company $company): RedirectResponse
    {
        $this->authorize('delete', $company);

        AuditLog::record('company.deleted', $company);

        $company->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company deleted.')]);

        return to_route('companies.index');
    }

    public function reactivate(Company $company): RedirectResponse
    {
        $this->authorize('update', $company);

        $company->update(['is_active' => true]);

        AuditLog::record('company.activated', $company);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company reactivated.')]);

        return to_route('companies.index');
    }

    /**
     * Log a company update as an activation/deactivation when that's what changed,
     * otherwise a generic update with a redacted diff (never the raw api_key value).
     */
    private function recordCompanyUpdate(Company $company): void
    {
        if ($company->wasChanged('is_active')) {
            AuditLog::record($company->is_active ? 'company.activated' : 'company.deactivated', $company);

            return;
        }

        AuditLog::record('company.updated', $company, Arr::except($company->getChanges(), ['api_key', 'updated_at']));
    }

    /**
     * @param  array{success: bool, pulled: int, deleted: int, message: string}  $result
     */
    private function recordJobRun(Company $company, array $result, string $triggeredBy): void
    {
        JobRun::create([
            'company_id' => $company->id,
            'triggered_by' => $triggeredBy,
            'success' => $result['success'],
            'pulled' => $result['pulled'],
            'deleted' => $result['deleted'] ?? 0,
            'message' => $result['message'],
        ]);
    }

    public function pullData(Company $company, CompanyLeadsSyncer $syncer, CompanyDirectorySyncer $directorySyncer): RedirectResponse
    {
        $this->authorize('update', $company);

        if (! $company->is_active) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Cannot pull data for an inactive company.')]);

            return to_route('companies.index');
        }

        $result = $syncer->sync($company);

        $this->recordJobRun($company, $result, 'manual');

        if (filled($company->affiliates_url)) {
            $this->recordJobRun($company, $directorySyncer->syncAffiliates($company), 'manual');
        }

        if (filled($company->advertisers_url)) {
            $this->recordJobRun($company, $directorySyncer->syncAdvertisers($company), 'manual');
        }

        Inertia::flash('toast', [
            'type' => $result['success'] ? 'success' : 'error',
            'message' => $result['message'],
        ]);

        return to_route('companies.index');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Company::class);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:companies,id'],
        ]);

        $deleted = Company::whereIn('id', $validated['ids'])
            ->get()
            ->filter(fn (Company $company) => $request->user()->can('delete', $company))
            ->each(function (Company $company) {
                AuditLog::record('company.deleted', $company);
                $company->delete();
            })
            ->count();

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{0} No companies deleted.|{1} :count company deleted.|[2,*] :count companies deleted.', $deleted, ['count' => $deleted])]);

        return to_route('companies.index');
    }
}
