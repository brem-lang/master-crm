<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage-users');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('manage-users') && $this->sharesScope($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->can('manage-users');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('manage-users') && $this->sharesScope($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can('manage-users') && $user->isNot($model) && $this->sharesScope($user, $model);
    }

    public function impersonate(User $user, User $model): bool
    {
        return $user->can('impersonate-users')
            && $user->isNot($model)
            && $this->sharesScope($user, $model);
    }

    /**
     * A user with no company (Parent Admin) is unscoped; otherwise the target
     * must belong to the acting user's own company.
     */
    private function sharesScope(User $user, User $model): bool
    {
        return $user->company_id === null || $user->company_id === $model->company_id;
    }
}
