<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Company::class);

        $search = trim((string) $request->query('search', ''));
        $action = $request->query('action');

        $scoped = fn () => AuditLog::query()
            ->when($action, fn ($query) => $query->where('action', $action))
            ->when($search !== '', fn ($query) => $query->whereHas('actor', fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")));

        $stats = $scoped()->selectRaw("
            count(*) as total,
            sum(case when action like '%.created' then 1 else 0 end) as created,
            sum(case when action like '%.deleted' or action like '%.removed_from_company' then 1 else 0 end) as deleted,
            sum(case when action not like '%.created' and action not like '%.deleted' and action not like '%.removed_from_company' then 1 else 0 end) as updated
        ")->first();

        return Inertia::render('audit-log/index', [
            'stats' => [
                'total' => (int) $stats->total,
                'created' => (int) $stats->created,
                'updated' => (int) $stats->updated,
                'deleted' => (int) $stats->deleted,
            ],
            'entries' => $scoped()
                ->with('actor:id,name,email')
                ->latest()
                ->paginate($this->perPage($request))
                ->withQueryString(),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'filters' => [
                'search' => $search,
                'action' => $action,
            ],
        ]);
    }
}
