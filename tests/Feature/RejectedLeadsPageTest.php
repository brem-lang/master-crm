<?php

use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('a child admin sees only rejected leads scoped to their own company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    Lead::factory()->count(2)->create(['company_id' => $company->id, 'status' => 'rejected']);
    Lead::factory()->count(1)->create(['company_id' => $company->id, 'status' => 'contacted']);
    Lead::factory()->count(5)->create(['company_id' => $otherCompany->id, 'status' => 'rejected']);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('leads.rejected'));

    $response->assertOk();
    $props = $response->inertiaPage()['props'];

    expect($props['total'])->toBe(2);
    expect($props['leads']['total'])->toBe(2);
    expect(collect($props['leads']['data'])->pluck('status')->unique()->all())->toBe(['rejected']);
    expect($props)->not->toHaveKey('companies');
});

test('a sales rep cannot view the rejected leads page', function () {
    $company = Company::factory()->create();
    Lead::factory()->count(3)->create(['company_id' => $company->id, 'status' => 'rejected']);

    $salesRep = User::factory()->create(['company_id' => $company->id]);
    $salesRep->assignRole('sales-rep');

    $response = $this->actingAs($salesRep)->get(route('leads.rejected'));

    $response->assertForbidden();
});

test('a parent admin sees rejected leads across all companies with a company filter list', function () {
    $companyA = Company::factory()->create(['name' => 'Company A']);
    $companyB = Company::factory()->create(['name' => 'Company B']);

    Lead::factory()->count(2)->create(['company_id' => $companyA->id, 'status' => 'rejected']);
    Lead::factory()->count(3)->create(['company_id' => $companyB->id, 'status' => 'rejected']);
    Lead::factory()->count(4)->create(['company_id' => $companyB->id, 'status' => 'new']);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->get(route('leads.rejected'));

    $response->assertOk();
    $props = $response->inertiaPage()['props'];

    expect($props['total'])->toBe(5);
    expect($props['companies'])->toHaveCount(2);
    expect($props['leads']['data'][0]['company']['name'])->not->toBeNull();
});

test('a parent admin can filter the rejected leads page down to one company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    Lead::factory()->count(2)->create(['company_id' => $companyA->id, 'status' => 'rejected']);
    Lead::factory()->count(3)->create(['company_id' => $companyB->id, 'status' => 'rejected']);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->get(route('leads.rejected', ['company_id' => $companyA->id]));

    $props = $response->inertiaPage()['props'];
    expect($props['total'])->toBe(2);
    expect($props['leads']['total'])->toBe(2);
});

test('search narrows the rejected leads list', function () {
    $company = Company::factory()->create();

    Lead::factory()->create(['company_id' => $company->id, 'first_name' => 'Alice', 'status' => 'rejected']);
    Lead::factory()->create(['company_id' => $company->id, 'first_name' => 'Bob', 'status' => 'rejected']);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('leads.rejected', ['search' => 'Alice']));
    $leads = $response->inertiaPage()['props']['leads']['data'];
    expect($leads)->toHaveCount(1);
    expect($leads[0]['first_name'])->toBe('Alice');
});

test('the shared rejectedLeadsCount prop is scoped like the rejected leads page itself', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    Lead::factory()->count(2)->create(['company_id' => $company->id, 'status' => 'rejected']);
    Lead::factory()->count(5)->create(['company_id' => $otherCompany->id, 'status' => 'rejected']);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('dashboard'));
    expect($response->inertiaPage()['props']['rejectedLeadsCount'])->toBe(2);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->get(route('dashboard'));
    expect($response->inertiaPage()['props']['rejectedLeadsCount'])->toBe(7);
});

test('the shared rejectedLeadsCount prop is null for a user without leads access', function () {
    $company = Company::factory()->create();
    Lead::factory()->count(3)->create(['company_id' => $company->id, 'status' => 'rejected']);

    $salesRep = User::factory()->create(['company_id' => $company->id]);
    $salesRep->assignRole('sales-rep');

    $response = $this->actingAs($salesRep)->get(route('dashboard'));

    expect($response->inertiaPage()['props']['rejectedLeadsCount'])->toBeNull();
});

test('a non-rejected lead never appears on the rejected leads page', function () {
    $company = Company::factory()->create();

    Lead::factory()->create(['company_id' => $company->id, 'first_name' => 'Alice', 'status' => 'rejected']);
    Lead::factory()->create(['company_id' => $company->id, 'first_name' => 'Bob', 'status' => 'converted']);
    Lead::factory()->create(['company_id' => $company->id, 'first_name' => 'Carol', 'status' => null]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('leads.rejected'));
    $names = collect($response->inertiaPage()['props']['leads']['data'])->pluck('first_name')->all();

    expect($names)->toBe(['Alice']);
});
