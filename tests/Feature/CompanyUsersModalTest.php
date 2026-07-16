<?php

use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('parent admin viewing a company receives its users as inertia props', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    $companyUser = User::factory()->create(['company_id' => $company->id]);
    $companyUser->assignRole('sales-rep');

    User::factory()->create(['company_id' => $otherCompany->id])->assignRole('sales-rep');

    $response = $this->actingAs($parentAdmin)->get(route('companies.index', ['view_company' => $company->id]));

    $response->assertOk();
    $page = $response->inertiaPage()['props'];

    expect($page['viewCompany']['id'])->toBe($company->id);
    expect($page['companyUsers'])->toHaveCount(1);
    expect($page['companyUsers'][0]['id'])->toBe($companyUser->id);
});

test('parent admin can create a user directly into a company', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $company = Company::factory()->create();

    $response = $this->actingAs($parentAdmin)->post(route('companies.users.store', $company), [
        'name' => 'New Agent',
        'email' => 'new-agent@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'sales-rep',
    ]);

    $response->assertRedirect();

    $user = User::where('email', 'new-agent@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->company_id)->toBe($company->id);
    expect($user->hasRole('sales-rep'))->toBeTrue();
});

test('creating a company user cannot be assigned the parent-admin role', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $company = Company::factory()->create();

    $response = $this->actingAs($parentAdmin)->post(route('companies.users.store', $company), [
        'name' => 'New Admin',
        'email' => 'new-admin@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'parent-admin',
    ]);

    $response->assertSessionHasErrors('role');
});

test('parent admin can remove a user from a company without deleting the account', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $company = Company::factory()->create();
    $companyUser = User::factory()->create(['company_id' => $company->id]);
    $companyUser->assignRole('sales-rep');

    $response = $this->actingAs($parentAdmin)->delete(route('companies.users.destroy', [$company, $companyUser]));

    $response->assertRedirect();

    $companyUser->refresh();
    expect($companyUser)->not->toBeNull();
    expect($companyUser->company_id)->toBeNull();
});

test('removing a user via a mismatched company returns 404', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $companyUser = User::factory()->create(['company_id' => $company->id]);
    $companyUser->assignRole('sales-rep');

    $response = $this->actingAs($parentAdmin)->delete(route('companies.users.destroy', [$otherCompany, $companyUser]));

    $response->assertNotFound();
    expect($companyUser->fresh()->company_id)->toBe($company->id);
});

test('child admin cannot create a user via the company users endpoint', function () {
    $company = Company::factory()->create();

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->post(route('companies.users.store', $company), [
        'name' => 'New Agent',
        'email' => 'new-agent@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'sales-rep',
    ]);

    $response->assertForbidden();
});

test('child admin cannot remove a user via the company users endpoint', function () {
    $company = Company::factory()->create();

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $companyUser = User::factory()->create(['company_id' => $company->id]);
    $companyUser->assignRole('sales-rep');

    $response = $this->actingAs($childAdmin)->delete(route('companies.users.destroy', [$company, $companyUser]));

    $response->assertForbidden();
    expect($companyUser->fresh()->company_id)->toBe($company->id);
});
