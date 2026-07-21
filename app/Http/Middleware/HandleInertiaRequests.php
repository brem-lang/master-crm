<?php

namespace App\Http\Middleware;

use App\Models\Lead;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Lab404\Impersonate\Services\ImpersonateManager;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $companyScoped = $user?->company_id && $user->can('view-company-customers');
        $allCompanies = $user?->can('view-all-customers');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'permissions' => $user?->getAllPermissions()->pluck('name') ?? [],
                'impersonating' => app(ImpersonateManager::class)->isImpersonating(),
                'company' => $user?->company,
            ],
            'notifications' => [
                'unread_count' => $user?->unreadNotifications()->count() ?? 0,
            ],
            'rejectedLeadsCount' => $companyScoped || $allCompanies
                ? Lead::where('status', 'rejected')
                    ->when($companyScoped, fn ($query) => $query->where('company_id', $user->company_id))
                    ->count()
                : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
