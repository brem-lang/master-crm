<?php

use App\Jobs\PullAllCompaniesLeadsJob;
use App\Jobs\PullCompanyLeadsJob;
use App\Models\Company;
use App\Models\JobRun;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Http::preventStrayRequests();
});

test('the manual pull-data action refuses to run for an inactive company', function () {
    $company = Company::factory()->create([
        'api_url' => 'https://example.com/functions/v1/get-leads',
        'is_active' => false,
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    $response->assertRedirect(route('companies.index'));
    $response->assertInertiaFlash('toast.type', 'error');

    expect(JobRun::where('company_id', $company->id)->exists())->toBeFalse();
});

test('dispatching PullCompanyLeadsJob for an inactive company does no work', function () {
    $company = Company::factory()->create([
        'api_url' => 'https://example.com/functions/v1/get-leads',
        'is_active' => false,
    ]);

    PullCompanyLeadsJob::dispatchSync($company);

    expect(JobRun::where('company_id', $company->id)->exists())->toBeFalse();
});

test('PullAllCompaniesLeadsJob never dispatches a job for an inactive company', function () {
    Bus::fake();

    $active = Company::factory()->create(['is_active' => true]);
    Company::factory()->create(['is_active' => false]);

    (new PullAllCompaniesLeadsJob)->handle();

    Bus::assertDispatched(PullCompanyLeadsJob::class, fn (PullCompanyLeadsJob $job) => $job->company->id === $active->id);
    Bus::assertDispatchedTimes(PullCompanyLeadsJob::class, 1);
});
