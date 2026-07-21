<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyAuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $companyId = $request->user()->company_id;

        abort_unless($companyId && $request->user()->can('view-reports'), 403);

        $search = trim((string) $request->query('search', ''));
        $action = $request->query('action');

        $userIds = User::where('company_id', $companyId)->pluck('id');

        // Scoped to this company's own subject rows: itself, or one of its current
        // users. A user later removed from the company (`user.removed_from_company`)
        // drops out of $userIds and becomes invisible here — accepted limitation,
        // avoids a fragile cross-database JSON-path query on `changes`.
        $scoped = fn () => AuditLog::query()
            ->where(function ($query) use ($companyId, $userIds) {
                $query->where(fn ($query) => $query->where('subject_type', Company::class)->where('subject_id', $companyId))
                    ->orWhere(fn ($query) => $query->where('subject_type', User::class)->whereIn('subject_id', $userIds));
            })
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

        return Inertia::render('company/audit-log', [
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
            'actions' => $scoped()->distinct()->orderBy('action')->pluck('action'),
            'filters' => [
                'search' => $search,
                'action' => $action,
            ],
        ]);
    }
}
