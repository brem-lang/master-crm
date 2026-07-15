<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage-roles');
    }

    public function create(User $user): bool
    {
        return $user->can('manage-roles');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('manage-roles');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can('manage-roles') && ! in_array($role->name, ['parent-admin', 'child-admin', 'agent'], true);
    }
}
