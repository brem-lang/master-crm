<?php

use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('a child admin sees leads metrics scoped to only their own company, excluding rejected', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    Lead::factory()->count(2)->create(['company_id' => $company->id, 'status' => 'rejected', 'is_ftd' => false]);
    Lead::factory()->count(1)->create(['company_id' => $company->id, 'status' => 'contacted', 'is_ftd' => true]);
    Lead::factory()->count(5)->create(['company_id' => $otherCompany->id, 'status' => 'rejected']);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('leads.index'));

    $response->assertOk();
    $props = $response->inertiaPage()['props'];

    expect($props['total'])->toBe(1);
    expect($props['ftd'])->toBe(1);
    expect($props['leads']['total'])->toBe(1);
    expect($props)->not->toHaveKey('rejected');
    expect($props)->not->toHaveKey('companies');
});

test('a sales rep cannot view the leads page', function () {
    $company = Company::factory()->create();
    Lead::factory()->count(3)->create(['company_id' => $company->id]);

    $salesRep = User::factory()->create(['company_id' => $company->id]);
    $salesRep->assignRole('sales-rep');

    $response = $this->actingAs($salesRep)->get(route('leads.index'));

    $response->assertForbidden();
});

test('a parent admin sees leads across all companies with a company filter list', function () {
    $companyA = Company::factory()->create(['name' => 'Company A']);
    $companyB = Company::factory()->create(['name' => 'Company B']);

    Lead::factory()->count(2)->create(['company_id' => $companyA->id, 'status' => 'new']);
    Lead::factory()->count(3)->create(['company_id' => $companyB->id, 'status' => 'new']);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->get(route('leads.index'));

    $response->assertOk();
    $props = $response->inertiaPage()['props'];

    expect($props['total'])->toBe(5);
    expect($props['companies'])->toHaveCount(2);
    expect($props['leads']['data'][0]['company']['name'])->not->toBeNull();
});

test('a parent admin can filter the leads page down to one company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    Lead::factory()->count(2)->create(['company_id' => $companyA->id, 'status' => 'new']);
    Lead::factory()->count(3)->create(['company_id' => $companyB->id, 'status' => 'new']);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->get(route('leads.index', ['company_id' => $companyA->id]));

    $props = $response->inertiaPage()['props'];
    expect($props['total'])->toBe(2);
    expect($props['leads']['total'])->toBe(2);
});

test('search and status filters narrow the leads list', function () {
    $company = Company::factory()->create();

    Lead::factory()->create(['company_id' => $company->id, 'first_name' => 'Alice', 'status' => 'new']);
    Lead::factory()->create(['company_id' => $company->id, 'first_name' => 'Bob', 'status' => 'contacted']);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('leads.index', ['search' => 'Alice']));
    $leads = $response->inertiaPage()['props']['leads']['data'];
    expect($leads)->toHaveCount(1);
    expect($leads[0]['first_name'])->toBe('Alice');

    $response = $this->actingAs($childAdmin)->get(route('leads.index', ['status' => 'contacted']));
    $leads = $response->inertiaPage()['props']['leads']['data'];
    expect($leads)->toHaveCount(1);
    expect($leads[0]['first_name'])->toBe('Bob');
});

test('the dashboard no longer shows leads data', function () {
    $company = Company::factory()->create();
    Lead::factory()->count(3)->create(['company_id' => $company->id]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('dashboard'));

    $response->assertOk();
    expect($response->inertiaPage()['props'])->not->toHaveKey('total');
});
