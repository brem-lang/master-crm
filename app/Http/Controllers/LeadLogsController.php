<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\LeadLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadLogsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Company::class);

        $search = trim((string) $request->query('search', ''));
        $offer = $request->query('offer');

        return Inertia::render('leads-logs/index', [
            'entries' => LeadLog::query()
                ->when($offer, fn ($query) => $query->where('offer_name', $offer))
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('email', 'like', "%{$search}%")
                    ->orWhere('offer_name', 'like', "%{$search}%")))
                ->latest('id')
                ->paginate($this->perPage($request))
                ->withQueryString(),
            'offers' => LeadLog::query()->distinct()->orderBy('offer_name')->pluck('offer_name'),
            'filters' => [
                'search' => $search,
                'offer' => $offer,
            ],
        ]);
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Company::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'max:'.max(self::PER_PAGE_OPTIONS)],
            'ids.*' => ['integer', 'distinct'],
        ]);

        // Single DELETE ... WHERE id IN (...) — unknown ids are simply ignored,
        // so no per-id `exists` lookups are needed.
        $deleted = LeadLog::whereIn('id', $validated['ids'])->delete();

        if ($deleted > 0) {
            AuditLog::create([
                'actor_id' => $request->user()->id,
                'ip_address' => $request->ip(),
                'action' => 'lead_logs.bulk_deleted',
                'subject_type' => (new LeadLog)->getMorphClass(),
                'changes' => ['ids' => $validated['ids'], 'count' => $deleted],
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{0} No lead logs deleted.|{1} :count lead log deleted.|[2,*] :count lead logs deleted.', $deleted, ['count' => $deleted])]);

        return back();
    }
}
