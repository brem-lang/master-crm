<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleStoreRequest;
use App\Http\Requests\Admin\RoleUpdateRequest;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    protected const BUILT_IN_ROLES = ['parent-admin', 'child-admin', 'sales-rep'];

    protected const PERMISSION_DESCRIPTIONS = [
        'manage-companies' => 'Create, edit, and delete companies across the platform.',
        'manage-users' => 'Create, edit, and delete user accounts.',
        'impersonate-users' => 'Log in as another user to troubleshoot on their behalf.',
        'manage-roles' => 'Create, edit, and delete roles and their permissions.',
        'view-all-customers' => 'View customer records across all companies.',
        'view-company-customers' => "View customer records within the user's own company.",
        'view-reports' => 'Access global reports and dashboards.',
        'manage-settings' => 'Modify company-wide settings.',
        'manage-leads' => 'Create, edit, and assign leads.',
        'log-activities' => 'Record activity notes against leads and customers.',
        'manage-own-company' => "Administer settings and data within the user's own company.",
        'assign-leads' => 'Assign leads to sales reps.',
        'send-test-leads' => 'Send test leads to advertisers.',
        'release-ftd' => 'Release first-time-deposit leads.',
        'resend-leads' => 'Resend leads to a chosen affiliate and advertiser.',
        'delete-leads' => 'Permanently delete leads.',
        'delete-affiliates' => 'Permanently delete affiliates.',
        'delete-advertisers' => 'Permanently delete advertisers.',
        'delete-rejected-leads' => 'Permanently delete rejected leads.',
        'view-system-logs' => 'View the application log file for troubleshooting.',
    ];

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
            'permissions' => Permission::pluck('name')->map(fn ($name) => [
                'name' => $name,
                'description' => self::PERMISSION_DESCRIPTIONS[$name] ?? null,
            ]),
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

        AuditLog::record('role.created', $role, ['permissions' => $role->permissions()->pluck('name')->all()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return to_route('roles.index');
    }

    public function update(RoleUpdateRequest $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        $previousPermissions = $role->permissions()->pluck('name');

        if (! in_array($role->name, self::BUILT_IN_ROLES, true)) {
            $role->name = $request->validated('name');
            $role->save();
        }

        $role->syncPermissions($request->validated('permissions') ?? []);

        $newPermissions = $role->permissions()->pluck('name');

        if ($newPermissions->diff($previousPermissions)->isNotEmpty() || $previousPermissions->diff($newPermissions)->isNotEmpty()) {
            AuditLog::record('role.updated', $role, [
                'from' => $previousPermissions->all(),
                'to' => $newPermissions->all(),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('roles.index');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        AuditLog::record('role.deleted', $role);

        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('roles.index');
    }
}
