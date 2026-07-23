<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\JobRun;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Company::class);

        $status = $request->query('status');
        $companyId = $request->integer('company_id') ?: null;

        $scoped = fn () => JobRun::query()
            ->when($status, fn ($query) => $query->where('success', $status === 'success'))
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId));

        $stats = $scoped()
            ->selectRaw('
                count(*) as total,
                sum(case when success = 1 then 1 else 0 end) as successful,
                sum(case when success = 0 then 1 else 0 end) as failed,
                sum(pulled) as pulled,
                sum(deleted) as deleted
            ')
            ->first();

        return Inertia::render('jobs/index', [
            'stats' => [
                'total' => (int) $stats->total,
                'successful' => (int) $stats->successful,
                'failed' => (int) $stats->failed,
                'pulled' => (int) $stats->pulled,
                'deleted' => (int) $stats->deleted,
            ],
            'runs' => $scoped()
                ->with('company:id,name')
                ->latest()
                ->paginate($this->perPage($request))
                ->withQueryString(),
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'filters' => [
                'status' => $status,
                'company_id' => $companyId,
            ],
        ]);
    }
}
