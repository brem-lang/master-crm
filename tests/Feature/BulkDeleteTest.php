<?php

use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('parent admin can bulk delete companies', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $companies = Company::factory()->count(3)->create();

    $response = $this->actingAs($parentAdmin)->delete(route('companies.bulk-destroy'), [
        'ids' => $companies->pluck('id')->all(),
    ]);

    $response->assertRedirect(route('companies.index'));
    expect(Company::count())->toBe(0);
});

test('non parent admin cannot bulk delete companies', function () {
    $company = Company::factory()->create();

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $others = Company::factory()->count(2)->create();

    $response = $this->actingAs($childAdmin)->delete(route('companies.bulk-destroy'), [
        'ids' => $others->pluck('id')->all(),
    ]);

    $response->assertForbidden();
    expect(Company::count())->toBe(3);
});

test('child admin bulk deleting users only deletes users within their own company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $childAdmin = User::factory()->create(['company_id' => $companyA->id]);
    $childAdmin->assignRole('child-admin');

    $ownAgent = User::factory()->create(['company_id' => $companyA->id]);
    $ownAgent->assignRole('sales-rep');

    $otherCompanyUser = User::factory()->create(['company_id' => $companyB->id]);
    $otherCompanyUser->assignRole('sales-rep');

    $response = $this->actingAs($childAdmin)->delete(route('users.bulk-destroy'), [
        'ids' => [$ownAgent->id, $otherCompanyUser->id],
    ]);

    $response->assertRedirect(route('users.index'));
    expect(User::find($ownAgent->id))->toBeNull();
    expect(User::find($otherCompanyUser->id))->not->toBeNull();
});

test('bulk delete requires a valid ids array', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->delete(route('users.bulk-destroy'), [
        'ids' => 'not-an-array',
    ]);

    $response->assertSessionHasErrors('ids');
});
