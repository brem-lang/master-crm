<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class ImpersonateController extends Controller
{
    public function start(User $user): RedirectResponse
    {
        $this->authorize('impersonate', $user);

        auth()->user()->impersonate($user);

        return to_route('dashboard');
    }

    public function stop(): RedirectResponse
    {
        auth()->user()->leaveImpersonation();

        return to_route('users.index');
    }
}
