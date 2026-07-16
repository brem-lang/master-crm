<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\WebsiteHealthChecker;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyDirectoryController extends Controller
{
    public function __construct(private readonly WebsiteHealthChecker $healthChecker) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Company::class);

        $search = trim((string) $request->query('search', ''));

        $companies = Company::query()
            ->where('is_active', true)
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'website']);

        $statuses = $this->healthChecker->statusFor($companies);

        $companies->each(
            fn (Company $company) => $company->setAttribute('website_status', $statuses[$company->id] ?? null),
        );

        return Inertia::render('companies/directory', [
            'companies' => $companies,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }
}
