<?php

use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('parent admin sees users across all companies', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    User::factory()->create(['company_id' => $companyA->id])->assignRole('child-admin');
    User::factory()->create(['company_id' => $companyB->id])->assignRole('child-admin');

    $response = $this->actingAs($parentAdmin)->get(route('users.index'));

    $response->assertOk();
    expect($response->inertiaPage()['props']['users']['total'])->toBe(3);
});

test('parent admin sees the parent-admin role in the assignable roles list', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->get(route('users.index'));

    $response->assertOk();
    expect($response->inertiaPage()['props']['roles'])->toContain('parent-admin');
});

test('child admin does not see the parent-admin role in the assignable roles list', function () {
    $company = Company::factory()->create();
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('users.index'));

    $response->assertOk();
    expect($response->inertiaPage()['props']['roles'])->not->toContain('parent-admin');
});

test('child admin only sees users in their own company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $childAdmin = User::factory()->create(['company_id' => $companyA->id]);
    $childAdmin->assignRole('child-admin');

    User::factory()->create(['company_id' => $companyA->id])->assignRole('sales-rep');
    User::factory()->create(['company_id' => $companyB->id])->assignRole('sales-rep');

    $response = $this->actingAs($childAdmin)->get(route('users.index'));

    $response->assertOk();
    expect($response->inertiaPage()['props']['users']['total'])->toBe(2);
});

test('child admin cannot update a user from a different company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $childAdmin = User::factory()->create(['company_id' => $companyA->id]);
    $childAdmin->assignRole('child-admin');

    $otherCompanyUser = User::factory()->create(['company_id' => $companyB->id]);
    $otherCompanyUser->assignRole('sales-rep');

    $response = $this->actingAs($childAdmin)->put(route('users.update', $otherCompanyUser), [
        'name' => 'Hacked Name',
        'email' => $otherCompanyUser->email,
        'role' => 'sales-rep',
    ]);

    $response->assertForbidden();
    expect($otherCompanyUser->fresh()->name)->not->toBe('Hacked Name');
});

test('child admin cannot assign the parent-admin role', function () {
    $company = Company::factory()->create();

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->post(route('users.store'), [
        'name' => 'New User',
        'email' => 'new-user@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'parent-admin',
    ]);

    $response->assertSessionHasErrors('role');
});

test('parent admin can assign a new user to a company on creation', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $company = Company::factory()->create();

    $response = $this->actingAs($parentAdmin)->post(route('users.store'), [
        'name' => 'New User',
        'email' => 'new-user@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'child-admin',
        'company_id' => (string) $company->id,
    ]);

    $response->assertRedirect(route('users.index'));
    expect(User::where('email', 'new-user@example.com')->first()->company_id)->toBe($company->id);
});

test('child admin created users are forced into the acting child admin own company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->post(route('users.store'), [
        'name' => 'New Agent',
        'email' => 'new-agent@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'sales-rep',
        'company_id' => (string) $otherCompany->id,
    ]);

    $response->assertRedirect(route('users.index'));
    expect(User::where('email', 'new-agent@example.com')->first()->company_id)->toBe($company->id);
});

test('parent admin can unassign a company from a user', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $company = Company::factory()->create();
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($parentAdmin)->put(route('users.update', $childAdmin), [
        'name' => $childAdmin->name,
        'email' => $childAdmin->email,
        'role' => 'child-admin',
        'company_id' => '',
    ]);

    $response->assertRedirect(route('users.index'));
    expect($childAdmin->fresh()->company_id)->toBeNull();
});

test('child admin cannot impersonate a user outside their company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $childAdmin = User::factory()->create(['company_id' => $companyA->id]);
    $childAdmin->assignRole('child-admin');

    $otherCompanyUser = User::factory()->create(['company_id' => $companyB->id]);
    $otherCompanyUser->assignRole('sales-rep');

    $response = $this->actingAs($childAdmin)->post(route('users.impersonate.start', $otherCompanyUser));

    $response->assertForbidden();
});

test('parent admin can impersonate another parent admin', function () {
    $parentAdminA = User::factory()->create();
    $parentAdminA->assignRole('parent-admin');

    $parentAdminB = User::factory()->create();
    $parentAdminB->assignRole('parent-admin');

    $response = $this->actingAs($parentAdminA)->post(route('users.impersonate.start', $parentAdminB));

    $response->assertRedirect(route('dashboard'));
});

test('child admin still cannot impersonate a parent admin', function () {
    $company = Company::factory()->create();

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($childAdmin)->post(route('users.impersonate.start', $parentAdmin));

    $response->assertForbidden();
});
