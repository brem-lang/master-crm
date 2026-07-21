<?php

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('a child admin sees entries for their own company and its users, not other companies', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    $companyUser = User::factory()->create(['company_id' => $company->id]);
    $otherCompanyUser = User::factory()->create(['company_id' => $otherCompany->id]);

    AuditLog::factory()->create(['action' => 'company.updated', 'subject_type' => Company::class, 'subject_id' => $company->id]);
    AuditLog::factory()->create(['action' => 'user.created', 'subject_type' => User::class, 'subject_id' => $companyUser->id]);
    AuditLog::factory()->create(['action' => 'company.updated', 'subject_type' => Company::class, 'subject_id' => $otherCompany->id]);
    AuditLog::factory()->create(['action' => 'user.created', 'subject_type' => User::class, 'subject_id' => $otherCompanyUser->id]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('company-audit-log.index'));

    $response->assertOk();
    $props = $response->inertiaPage()['props'];
    expect($props['stats']['total'])->toBe(2);

    $entries = collect($props['entries']['data']);
    $otherCompanyEntry = $entries->first(fn ($entry) => $entry['subject_type'] === Company::class && $entry['subject_id'] === $otherCompany->id);
    $otherUserEntry = $entries->first(fn ($entry) => $entry['subject_type'] === User::class && $entry['subject_id'] === $otherCompanyUser->id);

    expect($otherCompanyEntry)->toBeNull();
    expect($otherUserEntry)->toBeNull();
});

test('role entries never appear in the company-scoped activity log', function () {
    $company = Company::factory()->create();
    $role = Role::firstOrCreate(['name' => 'auditor']);

    AuditLog::factory()->create(['action' => 'role.created', 'subject_type' => $role::class, 'subject_id' => $role->id]);
    AuditLog::factory()->create(['action' => 'company.updated', 'subject_type' => Company::class, 'subject_id' => $company->id]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('company-audit-log.index'));

    $response->assertOk();
    $actions = collect($response->inertiaPage()['props']['entries']['data'])->pluck('action');
    expect($actions)->not->toContain('role.created');
});

test('a sales rep cannot view the company-scoped activity log', function () {
    $company = Company::factory()->create();
    $salesRep = User::factory()->create(['company_id' => $company->id]);
    $salesRep->assignRole('sales-rep');

    $response = $this->actingAs($salesRep)->get(route('company-audit-log.index'));

    $response->assertForbidden();
});
