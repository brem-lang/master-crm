<?php

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('creating a company records a company.created entry with the acting user', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->post(route('companies.store'), [
        'name' => 'Acme Inc',
        'api_url' => 'https://acme.example.com/api',
        'api_key' => 'secret-key',
        'is_active' => '1',
    ]);

    $company = Company::where('name', 'Acme Inc')->firstOrFail();

    $entry = AuditLog::where('action', 'company.created')->where('subject_id', $company->id)->first();
    expect($entry)->not->toBeNull();
    expect($entry->actor_id)->toBe($parentAdmin->id);
    expect($entry->subject_type)->toBe(Company::class);
    expect($entry->ip_address)->not->toBeNull();
});

test('deactivating a company records a company.deactivated entry, not a generic update', function () {
    $company = Company::factory()->create(['is_active' => true]);
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->put(route('companies.update', $company), [
        'name' => $company->name,
        'api_url' => $company->api_url,
        'api_key' => 'secret-key',
        'is_active' => '0',
    ]);

    expect(AuditLog::where('action', 'company.deactivated')->where('subject_id', $company->id)->exists())->toBeTrue();
    expect(AuditLog::where('action', 'company.updated')->where('subject_id', $company->id)->exists())->toBeFalse();
});

test('deleting a user records a user.deleted entry', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $target = User::factory()->create();
    $target->assignRole('sales-rep');

    $this->actingAs($parentAdmin)->delete(route('users.destroy', $target));

    expect(AuditLog::where('action', 'user.deleted')->where('subject_id', $target->id)->exists())->toBeTrue();
});

test('changing a user role records a user.role_changed entry with from/to', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $company = Company::factory()->create();
    $target = User::factory()->create(['company_id' => $company->id]);
    $target->assignRole('sales-rep');

    $this->actingAs($parentAdmin)->put(route('users.update', $target), [
        'name' => $target->name,
        'email' => $target->email,
        'role' => 'child-admin',
        'company_id' => (string) $company->id,
    ]);

    $entry = AuditLog::where('action', 'user.role_changed')->where('subject_id', $target->id)->first();
    expect($entry)->not->toBeNull();
    expect($entry->changes['from'])->toBe(['sales-rep']);
    expect($entry->changes['to'])->toBe(['child-admin']);
});

test('creating a role records permissions granted', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->post(route('roles.store'), [
        'name' => 'auditor',
        'permissions' => ['view-reports'],
    ]);

    $entry = AuditLog::where('action', 'role.created')->latest()->first();
    expect($entry)->not->toBeNull();
    expect($entry->changes['permissions'])->toBe(['view-reports']);
});

test('the activity log page is parent-admin only', function () {
    $company = Company::factory()->create();
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('audit-log.index'));

    $response->assertForbidden();
});

test('a parent admin sees paginated activity log entries with stats', function () {
    $company = Company::factory()->create();
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    AuditLog::factory()->create(['actor_id' => $parentAdmin->id, 'action' => 'company.created', 'subject_id' => $company->id]);
    AuditLog::factory()->create(['actor_id' => $parentAdmin->id, 'action' => 'company.deleted', 'subject_id' => $company->id]);

    $response = $this->actingAs($parentAdmin)->get(route('audit-log.index'));

    $response->assertOk();
    $props = $response->inertiaPage()['props'];
    expect($props['stats']['total'])->toBe(2);
    expect($props['stats']['created'])->toBe(1);
    expect($props['stats']['deleted'])->toBe(1);
});

test('bulk deleting companies logs one entry per deleted company', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $companies = Company::factory()->count(3)->create();

    $this->actingAs($parentAdmin)->delete(route('companies.bulk-destroy'), [
        'ids' => $companies->pluck('id')->all(),
    ]);

    expect(AuditLog::where('action', 'company.deleted')->count())->toBe(3);
});
