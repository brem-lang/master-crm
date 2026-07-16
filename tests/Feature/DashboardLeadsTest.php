<?php

use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('a child admin sees leads metrics scoped to only their own company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    Lead::factory()->count(2)->create(['company_id' => $company->id, 'status' => 'rejected', 'is_ftd' => false]);
    Lead::factory()->count(1)->create(['company_id' => $company->id, 'status' => 'contacted', 'is_ftd' => true]);
    Lead::factory()->count(5)->create(['company_id' => $otherCompany->id, 'status' => 'rejected']);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('dashboard'));

    $response->assertOk();
    $metrics = $response->inertiaPage()['props']['leadsMetrics'];

    expect($metrics)->not->toBeNull();
    expect($metrics['total'])->toBe(3);
    expect($metrics['rejected'])->toBe(2);
    expect($metrics['ftd'])->toBe(1);
    expect($metrics['leads']['total'])->toBe(3);
});

test('a sales rep does not see leads metrics', function () {
    $company = Company::factory()->create();
    Lead::factory()->count(3)->create(['company_id' => $company->id]);

    $salesRep = User::factory()->create(['company_id' => $company->id]);
    $salesRep->assignRole('sales-rep');

    $response = $this->actingAs($salesRep)->get(route('dashboard'));

    $response->assertOk();
    expect($response->inertiaPage()['props']['leadsMetrics'])->toBeNull();
});

test('a parent admin sees leads metrics across all companies with a company filter list', function () {
    $companyA = Company::factory()->create(['name' => 'Company A']);
    $companyB = Company::factory()->create(['name' => 'Company B']);

    Lead::factory()->count(2)->create(['company_id' => $companyA->id]);
    Lead::factory()->count(3)->create(['company_id' => $companyB->id]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->get(route('dashboard'));

    $response->assertOk();
    $metrics = $response->inertiaPage()['props']['leadsMetrics'];

    expect($metrics)->not->toBeNull();
    expect($metrics['total'])->toBe(5);
    expect($metrics['companies'])->toHaveCount(2);
    expect($metrics['leads']['data'][0]['company']['name'])->not->toBeNull();
});

test('a parent admin can filter the leads dashboard down to one company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    Lead::factory()->count(2)->create(['company_id' => $companyA->id]);
    Lead::factory()->count(3)->create(['company_id' => $companyB->id]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->get(route('dashboard', ['company_id' => $companyA->id]));

    $metrics = $response->inertiaPage()['props']['leadsMetrics'];
    expect($metrics['total'])->toBe(2);
    expect($metrics['leads']['total'])->toBe(2);
});

test('search and status filters narrow the leads list', function () {
    $company = Company::factory()->create();

    Lead::factory()->create(['company_id' => $company->id, 'first_name' => 'Alice', 'status' => 'rejected']);
    Lead::factory()->create(['company_id' => $company->id, 'first_name' => 'Bob', 'status' => 'contacted']);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('dashboard', ['search' => 'Alice']));
    $leads = $response->inertiaPage()['props']['leadsMetrics']['leads']['data'];
    expect($leads)->toHaveCount(1);
    expect($leads[0]['first_name'])->toBe('Alice');

    $response = $this->actingAs($childAdmin)->get(route('dashboard', ['status' => 'contacted']));
    $leads = $response->inertiaPage()['props']['leadsMetrics']['leads']['data'];
    expect($leads)->toHaveCount(1);
    expect($leads[0]['first_name'])->toBe('Bob');
});
