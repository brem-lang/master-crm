<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyLeadsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user->can('manage-leads'), 403);

        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');

        $scoped = fn () => Lead::query()->where('assigned_to', $user->id);

        $stats = $scoped()
            ->selectRaw('
                count(*) as total,
                sum(case when status = ? then 1 else 0 end) as rejected,
                sum(case when is_ftd = 1 then 1 else 0 end) as ftd
            ', ['rejected'])
            ->first();

        return Inertia::render('leads/my-leads', [
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
                ->latest('lead_created_at')
                ->paginate($this->perPage($request))
                ->withQueryString(),
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }
}
