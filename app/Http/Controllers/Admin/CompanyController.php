<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompanyStoreRequest;
use App\Http\Requests\Admin\CompanyUpdateRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(Request $request): Response
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
            'companies' => Company::withCount('users')
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                }))
                ->when($status === 'active', fn ($query) => $query->where('is_active', true))
                ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
                ->latest()
                ->paginate($this->perPage($request))
                ->withQueryString(),
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function store(CompanyStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Company::class);

        Company::create([
            ...$request->validated(),
            'slug' => Company::generateUniqueSlug($request->validated('name')),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company created.')]);

        return to_route('companies.index');
    }

    public function update(CompanyUpdateRequest $request, Company $company): RedirectResponse
    {
        $this->authorize('update', $company);

        $company->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company updated.')]);

        return to_route('companies.index');
    }

    public function destroy(Company $company): RedirectResponse
    {
        $this->authorize('delete', $company);

        $company->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company deleted.')]);

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
            ->each->delete()
            ->count();

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{0} No companies deleted.|{1} :count company deleted.|[2,*] :count companies deleted.', $deleted, ['count' => $deleted])]);

        return to_route('companies.index');
    }
}
