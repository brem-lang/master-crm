<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\JobRun;
use App\Services\CompanyLeadsSyncer;
use App\Support\CompanyHealth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CompanyHealthController extends Controller
{
    public function index(CompanyHealth $companyHealth): Response
    {
        abort_unless(Auth::user()->can('manage-own-company') && Auth::user()->company_id, 403);

        $company = Company::findOrFail(Auth::user()->company_id);

        $companyHealth->attach(collect([$company]));

        return Inertia::render('company/health', [
            'company' => $company,
        ]);
    }

    public function pullData(CompanyLeadsSyncer $syncer): RedirectResponse
    {
        abort_unless(Auth::user()->can('manage-own-company') && Auth::user()->company_id, 403);

        $company = Company::findOrFail(Auth::user()->company_id);

        if (! $company->is_active) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Cannot pull data for an inactive company.')]);

            return to_route('company-health.index');
        }

        $result = $syncer->sync($company);

        JobRun::create([
            'company_id' => $company->id,
            'triggered_by' => 'manual',
            'success' => $result['success'],
            'pulled' => $result['pulled'],
            'message' => $result['message'],
        ]);

        Inertia::flash('toast', [
            'type' => $result['success'] ? 'success' : 'error',
            'message' => $result['message'],
        ]);

        return to_route('company-health.index');
    }
}
