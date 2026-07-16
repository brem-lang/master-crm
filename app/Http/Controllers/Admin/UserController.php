<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserStoreRequest;
use App\Http\Requests\Admin\UserUpdateRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $actingUser = auth()->user();
        $isParentAdmin = $actingUser->hasRole('parent-admin');

        $search = trim((string) $request->query('search', ''));
        $role = $request->query('role');
        $companyId = $request->query('company_id');

        $scopedUsers = fn () => User::when($actingUser->company_id, fn ($query) => $query->where('company_id', $actingUser->company_id));

        return Inertia::render('admin/users/index', [
            'stats' => [
                'total' => $scopedUsers()->count(),
                'parent_admin' => $scopedUsers()->role('parent-admin')->count(),
                'child_admin' => $scopedUsers()->role('child-admin')->count(),
                'sales_rep' => $scopedUsers()->role('sales-rep')->count(),
            ],
            'users' => User::with(['roles', 'company'])
                ->when($actingUser->company_id, fn ($query) => $query->where('company_id', $actingUser->company_id))
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                }))
                ->when($role, fn ($query) => $query->whereHas('roles', fn ($query) => $query->where('name', $role)))
                ->when($isParentAdmin && $companyId, fn ($query) => $query->where('company_id', $companyId))
                ->latest()
                ->paginate($this->perPage($request))
                ->withQueryString(),
            'roles' => Role::pluck('name'),
            'companies' => $isParentAdmin
                ? Company::where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : [],
            'filters' => [
                'search' => $search,
                'role' => $role,
                'company_id' => $companyId,
            ],
        ]);
    }

    public function store(UserStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $actingUser = $request->user();

        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'email_verified_at' => now(),
        ]);

        $user->company_id = $actingUser->hasRole('parent-admin')
            ? $request->validated('company_id')
            : $actingUser->company_id;
        $user->save();

        $user->assignRole($request->validated('role'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return to_route('users.index');
    }

    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $actingUser = $request->user();

        $user->fill([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
        ]);

        if ($actingUser->hasRole('parent-admin')) {
            $user->company_id = $request->validated('company_id');
        }

        if ($request->validated('password')) {
            $user->password = Hash::make($request->validated('password'));
        }

        $user->save();

        $user->syncRoles([$request->validated('role')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('users.index');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return to_route('users.index');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', User::class);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:users,id'],
        ]);

        $deleted = User::whereIn('id', $validated['ids'])
            ->get()
            ->filter(fn (User $user) => $request->user()->can('delete', $user))
            ->each->delete()
            ->count();

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{0} No users deleted.|{1} :count user deleted.|[2,*] :count users deleted.', $deleted, ['count' => $deleted])]);

        return to_route('users.index');
    }
}
