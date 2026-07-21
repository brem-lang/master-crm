<?php

use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('api_key is never present in the companies index page props', function () {
    Company::factory()->create(['api_key' => 'super-secret-key']);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->get(route('companies.index'));

    $response->assertOk();
    $json = json_encode($response->inertiaPage()['props']['companies']['data']);
    expect($json)->not->toContain('super-secret-key');
});

test('api_key is never present in the shared auth.company prop for a child admin', function () {
    $company = Company::factory()->create(['api_key' => 'super-secret-key']);
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('dashboard'));

    $response->assertOk();
    $json = json_encode($response->inertiaPage()['props']['auth']);
    expect($json)->not->toContain('super-secret-key');
});

test('api_key is never present in the company-health page props', function () {
    $company = Company::factory()->create(['api_key' => 'super-secret-key']);
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('company-health.index'));

    $response->assertOk();
    $json = json_encode($response->inertiaPage()['props']['company']);
    expect($json)->not->toContain('super-secret-key');
});

test('leaving api_key blank on update keeps the existing key', function () {
    $company = Company::factory()->create(['api_key' => 'original-key']);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->put(route('companies.update', $company), [
        'name' => $company->name,
        'api_url' => $company->api_url,
        'api_key' => '',
        'is_active' => '1',
    ]);

    expect($company->fresh()->api_key)->toBe('original-key');
});

test('providing a new api_key on update replaces the existing key', function () {
    $company = Company::factory()->create(['api_key' => 'original-key']);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->put(route('companies.update', $company), [
        'name' => $company->name,
        'api_url' => $company->api_url,
        'api_key' => 'brand-new-key',
        'is_active' => '1',
    ]);

    expect($company->fresh()->api_key)->toBe('brand-new-key');
});

test('global search requires view-all-customers, not just manage-companies', function () {
    $company = Company::factory()->create();
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->getJson(route('search.index', ['q' => 'test']));

    $response->assertForbidden();
});
