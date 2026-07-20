<?php

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('a parent admin can reactivate an inactive company', function () {
    $company = Company::factory()->create(['is_active' => false]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->patch(route('companies.reactivate', $company));

    $response->assertRedirect(route('companies.index'));
    expect($company->fresh()->is_active)->toBeTrue();

    expect(AuditLog::where('action', 'company.activated')->where('subject_id', $company->id)->exists())->toBeTrue();
});

test('a child admin cannot reactivate a company', function () {
    $company = Company::factory()->create(['is_active' => false]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->patch(route('companies.reactivate', $company));

    $response->assertForbidden();
    expect($company->fresh()->is_active)->toBeFalse();
});
