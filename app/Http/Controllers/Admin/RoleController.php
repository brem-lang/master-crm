<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleStoreRequest;
use App\Http\Requests\Admin\RoleUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    protected const BUILT_IN_ROLES = ['parent-admin', 'child-admin', 'agent'];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Role::class);

        $search = trim((string) $request->query('search', ''));
        $permission = $request->query('permission');

        return Inertia::render('admin/roles/index', [
            'stats' => [
                'total' => Role::count(),
                'permissions' => Permission::count(),
            ],
            'roles' => Role::with('permissions')
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->when($permission, fn ($query) => $query->whereHas('permissions', fn ($query) => $query->where('name', $permission)))
                ->latest()
                ->paginate($this->perPage($request))
                ->withQueryString(),
            'permissions' => Permission::pluck('name'),
            'filters' => [
                'search' => $search,
                'permission' => $permission,
            ],
        ]);
    }

    public function store(RoleStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $role = Role::create(['name' => $request->validated('name')]);
        $role->syncPermissions($request->validated('permissions') ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return to_route('roles.index');
    }

    public function update(RoleUpdateRequest $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        if (! in_array($role->name, self::BUILT_IN_ROLES, true)) {
            $role->name = $request->validated('name');
            $role->save();
        }

        $role->syncPermissions($request->validated('permissions') ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('roles.index');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('roles.index');
    }
}
