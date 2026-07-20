<?php

use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('a child admin of an inactive company sees the inactive notice on the dashboard', function () {
    $company = Company::factory()->create(['is_active' => false]);
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('dashboard'));

    $response->assertOk();
    expect($response->inertiaPage()['component'])->toBe('company-inactive');
});

test('a sales rep of an inactive company sees the inactive notice on the leads page', function () {
    $company = Company::factory()->create(['is_active' => false]);
    $salesRep = User::factory()->create(['company_id' => $company->id]);
    $salesRep->assignRole('sales-rep');

    $response = $this->actingAs($salesRep)->get(route('leads.index'));

    $response->assertOk();
    expect($response->inertiaPage()['component'])->toBe('company-inactive');
});

test('users of an active company are unaffected', function () {
    $company = Company::factory()->create(['is_active' => true]);
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('dashboard'));

    $response->assertOk();
    expect($response->inertiaPage()['component'])->toBe('dashboard');
});

test('a parent admin is unaffected regardless of any company being inactive', function () {
    Company::factory()->create(['is_active' => false]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->get(route('dashboard'));

    $response->assertOk();
    expect($response->inertiaPage()['component'])->toBe('dashboard');
});

test('a user of an inactive company can still log out', function () {
    $company = Company::factory()->create(['is_active' => false]);
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->post(route('logout'));

    $response->assertRedirect(route('home'));
    $this->assertGuest();
});
