<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesRepLeaderboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user->company_id && $user->can('view-reports'), 403);

        $reps = User::where('company_id', $user->company_id)
            ->role('sales-rep')
            ->orderBy('name')
            ->get(['id', 'name']);

        $tallies = Lead::whereIn('assigned_to', $reps->pluck('id'))
            ->selectRaw('
                assigned_to,
                count(*) as total,
                sum(case when is_ftd = 1 then 1 else 0 end) as ftd
            ')
            ->groupBy('assigned_to')
            ->get()
            ->keyBy('assigned_to');

        $leaderboard = $reps->map(function (User $rep) use ($tallies) {
            $tally = $tallies->get($rep->id);
            $total = (int) ($tally->total ?? 0);
            $ftd = (int) ($tally->ftd ?? 0);

            return [
                'id' => $rep->id,
                'name' => $rep->name,
                'total' => $total,
                'ftd' => $ftd,
                'conversion_rate' => $total > 0 ? round($ftd / $total * 100, 1) : 0.0,
            ];
        })->sortByDesc('conversion_rate')->values();

        return Inertia::render('leads/leaderboard', [
            'leaderboard' => $leaderboard,
        ]);
    }
}
