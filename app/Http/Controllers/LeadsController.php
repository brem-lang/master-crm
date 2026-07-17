<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Lead;
use Illuminate\Http\Request;
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

        return Inertia::render('leads/index', $metrics);
    }

    /**
     * Build lead metrics/list for a single company, or across all companies when
     * `$companyId` is null (parent admin view).
     *
     * @return array<string, mixed>
     */
    private function leadsMetrics(Request $request, ?int $companyId): array
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');

        $scoped = fn () => Lead::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId));

        $stats = $scoped()
            ->selectRaw('
                count(*) as total,
                sum(case when status = ? then 1 else 0 end) as rejected,
                sum(case when is_ftd = 1 then 1 else 0 end) as ftd
            ', ['rejected'])
            ->first();

        return [
            'total' => (int) $stats->total,
            'rejected' => (int) $stats->rejected,
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
                ->when(! $companyId, fn ($query) => $query->with('company:id,name'))
                ->latest('lead_created_at')
                ->paginate($this->perPage($request))
                ->withQueryString(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'company_id' => $companyId,
            ],
        ];
    }
}
