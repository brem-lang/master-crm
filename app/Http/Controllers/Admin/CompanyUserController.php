<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompanyUserStoreRequest;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class CompanyUserController extends Controller
{
    public function store(CompanyUserStoreRequest $request, Company $company): RedirectResponse
    {
        $this->authorize('update', $company);
        $this->authorize('create', User::class);

        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'email_verified_at' => now(),
        ]);

        $user->company_id = $company->id;
        $user->save();

        $user->assignRole($request->validated('role'));

        AuditLog::record('user.created', $user, ['company_id' => $company->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User added to company.')]);

        return back();
    }

    public function destroy(Company $company, User $user): RedirectResponse
    {
        abort_unless($user->company_id === $company->id, 404);

        $this->authorize('update', $company);
        $this->authorize('update', $user);

        $user->company_id = null;
        $user->save();

        AuditLog::record('user.removed_from_company', $user, ['company_id' => $company->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User removed from company.')]);

        return back();
    }
}
