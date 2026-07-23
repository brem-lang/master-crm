<?php

namespace App\Http\Controllers;

use App\Models\Advertiser;
use App\Models\Company;
use App\Services\ChildCrmDirectoryClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdvertiserController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $companyScoped = $user->company_id && $user->can('view-company-customers');
        $allCompanies = $user->can('view-all-customers');

        abort_unless($companyScoped || $allCompanies, 403);

        $companyId = $companyScoped ? $user->company_id : ($request->integer('company_id') ?: null);

        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');

        $scoped = fn () => Advertiser::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId));

        $stats = $scoped()->selectRaw('
            count(*) as total,
            sum(case when is_active = 1 then 1 else 0 end) as active,
            sum(case when is_active = 0 then 1 else 0 end) as inactive
        ')->first();

        $props = [
            'stats' => [
                'total' => (int) $stats->total,
                'active' => (int) $stats->active,
                'inactive' => (int) $stats->inactive,
            ],
            'advertisers' => $scoped()
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->when($status === 'active', fn ($query) => $query->where('is_active', true))
                ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
                ->when(! $companyId, fn ($query) => $query->with('company:id,name'))
                ->latest()
                ->paginate($this->perPage($request))
                ->withQueryString(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'company_id' => $companyId,
            ],
        ];

        if (! $companyScoped) {
            $props['companies'] = Company::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        }

        return Inertia::render('advertisers/index', $props);
    }

    public function sendTestLead(Request $request, Advertiser $advertiser, ChildCrmDirectoryClient $client): JsonResponse
    {
        $user = $request->user();

        $companyScoped = $user->company_id && $user->can('view-company-customers');
        $allCompanies = $user->can('view-all-customers');

        abort_unless($user->can('send-test-leads') && ($companyScoped || $allCompanies), 403);
        abort_if($companyScoped && $advertiser->company_id !== $user->company_id, 403);

        $validated = $request->validate([
            'firstname' => ['nullable', 'string', 'max:255'],
            'lastname' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'mobile' => ['required', 'string', 'max:20'],
            'country_code' => ['required', 'string', 'size:2'],
            'country' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['required', 'ip'],
            'offer_name' => ['nullable', 'string', 'max:255'],
            'custom1' => ['nullable', 'string', 'max:255'],
            'custom2' => ['nullable', 'string', 'max:255'],
            'custom3' => ['nullable', 'string', 'max:255'],
            'locale' => ['nullable', 'string', 'max:35'],
            'password' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'max:10'],
        ]);

        $result = $client->sendTestLead($advertiser->company, [
            ...$validated,
            'advertiser_id' => $advertiser->external_id,
        ]);

        return response()->json($result['body'], $result['status']);
    }
}
