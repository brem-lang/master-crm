<?php

use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('company stats count all companies regardless of active filters applied to the table', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    Company::factory()->create(['is_active' => true]);
    Company::factory()->create(['is_active' => true]);
    Company::factory()->create(['is_active' => false]);

    $response = $this->actingAs($parentAdmin)->get(route('companies.index', ['status' => 'active']));

    $response->assertOk();
    $stats = $response->inertiaPage()['props']['stats'];
    expect($stats)->toBe(['total' => 3, 'active' => 2, 'inactive' => 1]);
});

test('parent admin user stats count users across all companies', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    User::factory()->create(['company_id' => $companyA->id])->assignRole('child-admin');
    User::factory()->create(['company_id' => $companyB->id])->assignRole('child-admin');
    User::factory()->create(['company_id' => $companyB->id])->assignRole('agent');

    $response = $this->actingAs($parentAdmin)->get(route('users.index'));

    $response->assertOk();
    $stats = $response->inertiaPage()['props']['stats'];
    expect($stats)->toBe([
        'total' => 4,
        'parent_admin' => 1,
        'child_admin' => 2,
        'agent' => 1,
    ]);
});

test('child admin user stats are scoped to their own company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $childAdmin = User::factory()->create(['company_id' => $companyA->id]);
    $childAdmin->assignRole('child-admin');

    User::factory()->create(['company_id' => $companyA->id])->assignRole('agent');
    User::factory()->create(['company_id' => $companyB->id])->assignRole('agent');

    $response = $this->actingAs($childAdmin)->get(route('users.index'));

    $response->assertOk();
    $stats = $response->inertiaPage()['props']['stats'];
    expect($stats)->toBe([
        'total' => 2,
        'parent_admin' => 0,
        'child_admin' => 1,
        'agent' => 1,
    ]);
});
