<?php

use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('companies can be filtered by search term', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    Company::factory()->create(['name' => 'Acme Corp']);
    Company::factory()->create(['name' => 'Globex Inc']);

    $response = $this->actingAs($parentAdmin)->get(route('companies.index', ['search' => 'acme']));

    $response->assertOk();
    $companies = $response->inertiaPage()['props']['companies'];
    expect($companies['total'])->toBe(1);
    expect($companies['data'][0]['name'])->toBe('Acme Corp');
});

test('companies can be filtered by status', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    Company::factory()->create(['is_active' => true]);
    Company::factory()->create(['is_active' => false]);

    $response = $this->actingAs($parentAdmin)->get(route('companies.index', ['status' => 'inactive']));

    $response->assertOk();
    $companies = $response->inertiaPage()['props']['companies'];
    expect($companies['total'])->toBe(1);
    expect($companies['data'][0]['is_active'])->toBeFalse();
});

test('users can be filtered by role', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $company = Company::factory()->create();
    User::factory()->create(['company_id' => $company->id])->assignRole('child-admin');
    User::factory()->create(['company_id' => $company->id])->assignRole('sales-rep');

    $response = $this->actingAs($parentAdmin)->get(route('users.index', ['role' => 'sales-rep']));

    $response->assertOk();
    $users = $response->inertiaPage()['props']['users'];
    expect($users['total'])->toBe(1);
});

test('users can be filtered by search term', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
    User::factory()->create(['name' => 'John Smith', 'email' => 'john@example.com']);

    $response = $this->actingAs($parentAdmin)->get(route('users.index', ['search' => 'jane']));

    $response->assertOk();
    $users = $response->inertiaPage()['props']['users'];
    expect($users['total'])->toBe(1);
    expect($users['data'][0]['name'])->toBe('Jane Doe');
});

test('roles can be filtered by search term', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    Role::create(['name' => 'custom-support']);

    $response = $this->actingAs($parentAdmin)->get(route('roles.index', ['search' => 'support']));

    $response->assertOk();
    $roles = $response->inertiaPage()['props']['roles'];
    expect($roles['total'])->toBe(1);
    expect($roles['data'][0]['name'])->toBe('custom-support');
});

test('roles can be filtered by permission', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->get(route('roles.index', ['permission' => 'manage-companies']));

    $response->assertOk();
    $roles = $response->inertiaPage()['props']['roles'];
    expect($roles['total'])->toBe(1);
    expect($roles['data'][0]['name'])->toBe('parent-admin');
});
