<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\LeadLog;
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
}
