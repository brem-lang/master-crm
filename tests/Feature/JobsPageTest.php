<?php

use App\Jobs\PullCompanyLeadsJob;
use App\Models\Company;
use App\Models\JobRun;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('a parent admin sees the jobs page with correct stats', function () {
    $company = Company::factory()->create();

    JobRun::factory()->create(['company_id' => $company->id, 'success' => true, 'pulled' => 5]);
    JobRun::factory()->create(['company_id' => $company->id, 'success' => false, 'pulled' => 0]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->get(route('jobs.index'));

    $response->assertOk();
    $props = $response->inertiaPage()['props'];

    expect($props['stats']['total'])->toBe(2);
    expect($props['stats']['successful'])->toBe(1);
    expect($props['stats']['failed'])->toBe(1);
    expect($props['stats']['pulled'])->toBe(5);
    expect($props['runs']['total'])->toBe(2);
});

test('a child admin cannot view the jobs page', function () {
    $company = Company::factory()->create();
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('jobs.index'));

    $response->assertForbidden();
});

test('a sales rep cannot view the jobs page', function () {
    $company = Company::factory()->create();
    $salesRep = User::factory()->create(['company_id' => $company->id]);
    $salesRep->assignRole('sales-rep');

    $response = $this->actingAs($salesRep)->get(route('jobs.index'));

    $response->assertForbidden();
});

test('clicking pull data records a manual job run', function () {
    Http::fake([
        '*' => Http::response(['success' => true, 'total_leads' => 0], 200),
    ]);

    $company = Company::factory()->create(['api_url' => 'https://example.com/functions/v1/get-leads']);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    $run = JobRun::where('company_id', $company->id)->first();
    expect($run)->not->toBeNull();
    expect($run->triggered_by)->toBe('manual');
    expect($run->attempt)->toBeNull();
});

test('dispatching the scheduled job records a scheduled job run with attempt number', function () {
    Http::fake([
        '*' => Http::response(['success' => true, 'total_leads' => 0], 200),
    ]);

    $company = Company::factory()->create(['api_url' => 'https://example.com/functions/v1/get-leads']);

    PullCompanyLeadsJob::dispatchSync($company);

    $run = JobRun::where('company_id', $company->id)->first();
    expect($run)->not->toBeNull();
    expect($run->triggered_by)->toBe('scheduled');
    expect($run->attempt)->toBe(1);
});

test('status and company filters narrow the job run list', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    JobRun::factory()->create(['company_id' => $companyA->id, 'success' => true]);
    JobRun::factory()->create(['company_id' => $companyA->id, 'success' => false]);
    JobRun::factory()->create(['company_id' => $companyB->id, 'success' => true]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->get(route('jobs.index', ['status' => 'failed']));
    expect($response->inertiaPage()['props']['runs']['total'])->toBe(1);

    $response = $this->actingAs($parentAdmin)->get(route('jobs.index', ['company_id' => $companyA->id]));
    expect($response->inertiaPage()['props']['runs']['total'])->toBe(2);
});
