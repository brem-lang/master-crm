<?php

use App\Models\Company;
use App\Models\JobRun;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Http::fake(['*' => Http::response('', 200)]);
});

test('a child admin sees their own company health', function () {
    $company = Company::factory()->create();
    JobRun::factory()->create(['company_id' => $company->id, 'success' => false]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('company-health.index'));

    $response->assertOk();
    $props = $response->inertiaPage()['props'];
    expect($props['company']['id'])->toBe($company->id);
    expect($props['company']['failure_streak'])->toBe(1);
});

test('a child admin can pull data for their own company', function () {
    Http::fake(['*' => Http::response(['success' => true, 'total_leads' => 0], 200)]);

    $company = Company::factory()->create(['api_url' => 'https://example.com/functions/v1/get-leads']);
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->post(route('company-health.pull-data'));

    $response->assertRedirect(route('company-health.index'));
    expect(JobRun::where('company_id', $company->id)->where('triggered_by', 'manual')->exists())->toBeTrue();
});

test('a child admin cannot pull data for an inactive company', function () {
    $company = Company::factory()->create(['is_active' => false]);
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->post(route('company-health.pull-data'));

    $response->assertRedirect(route('company-health.index'));
    $response->assertInertiaFlash('toast.type', 'error');
    expect(JobRun::where('company_id', $company->id)->exists())->toBeFalse();
});

test('a sales rep cannot view company health', function () {
    $company = Company::factory()->create();
    $salesRep = User::factory()->create(['company_id' => $company->id]);
    $salesRep->assignRole('sales-rep');

    $response = $this->actingAs($salesRep)->get(route('company-health.index'));

    $response->assertForbidden();
});
