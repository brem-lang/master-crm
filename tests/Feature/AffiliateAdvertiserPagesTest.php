<?php

use App\Models\Advertiser;
use App\Models\Affiliate;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('a child admin sees affiliates scoped to only their own company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    Affiliate::factory()->count(2)->create(['company_id' => $company->id, 'is_active' => true]);
    Affiliate::factory()->count(1)->create(['company_id' => $company->id, 'is_active' => false]);
    Affiliate::factory()->count(5)->create(['company_id' => $otherCompany->id]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('affiliates.index'));

    $response->assertOk();
    $props = $response->inertiaPage()['props'];

    expect($props['stats']['total'])->toBe(3);
    expect($props['stats']['active'])->toBe(2);
    expect($props['stats']['inactive'])->toBe(1);
    expect($props['affiliates']['total'])->toBe(3);
    expect($props)->not->toHaveKey('companies');
});

test('a sales rep cannot view the affiliates or advertisers page', function () {
    $company = Company::factory()->create();

    $salesRep = User::factory()->create(['company_id' => $company->id]);
    $salesRep->assignRole('sales-rep');

    $this->actingAs($salesRep)->get(route('affiliates.index'))->assertForbidden();
    $this->actingAs($salesRep)->get(route('advertisers.index'))->assertForbidden();
});

test('a parent admin sees affiliates across all companies with a company filter list', function () {
    $companyA = Company::factory()->create(['name' => 'Company A']);
    $companyB = Company::factory()->create(['name' => 'Company B']);

    Affiliate::factory()->count(2)->create(['company_id' => $companyA->id]);
    Affiliate::factory()->count(3)->create(['company_id' => $companyB->id]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->get(route('affiliates.index'));

    $response->assertOk();
    $props = $response->inertiaPage()['props'];

    expect($props['stats']['total'])->toBe(5);
    expect($props['companies'])->toHaveCount(2);
    expect($props['affiliates']['data'][0]['company']['name'])->not->toBeNull();
});

test('a parent admin can filter the affiliates page down to one company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    Affiliate::factory()->count(2)->create(['company_id' => $companyA->id]);
    Affiliate::factory()->count(3)->create(['company_id' => $companyB->id]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->get(route('affiliates.index', ['company_id' => $companyA->id]));

    $props = $response->inertiaPage()['props'];
    expect($props['stats']['total'])->toBe(2);
    expect($props['affiliates']['total'])->toBe(2);
});

test('search and status filters narrow the affiliates list', function () {
    $company = Company::factory()->create();

    Affiliate::factory()->create(['company_id' => $company->id, 'name' => 'Alice Affiliate', 'is_active' => true]);
    Affiliate::factory()->create(['company_id' => $company->id, 'name' => 'Bob Affiliate', 'is_active' => false]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('affiliates.index', ['search' => 'Alice']));
    $affiliates = $response->inertiaPage()['props']['affiliates']['data'];
    expect($affiliates)->toHaveCount(1);
    expect($affiliates[0]['name'])->toBe('Alice Affiliate');

    $response = $this->actingAs($childAdmin)->get(route('affiliates.index', ['status' => 'inactive']));
    $affiliates = $response->inertiaPage()['props']['affiliates']['data'];
    expect($affiliates)->toHaveCount(1);
    expect($affiliates[0]['name'])->toBe('Bob Affiliate');
});

test('the affiliates page never exposes api_key to the frontend', function () {
    $company = Company::factory()->create();
    Affiliate::factory()->create(['company_id' => $company->id, 'api_key' => 'super-secret-key']);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('affiliates.index'));
    $affiliate = $response->inertiaPage()['props']['affiliates']['data'][0];

    expect($affiliate)->not->toHaveKey('api_key');
});

test('a child admin sees advertisers scoped to only their own company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    Advertiser::factory()->count(2)->create(['company_id' => $company->id, 'is_active' => true]);
    Advertiser::factory()->count(1)->create(['company_id' => $company->id, 'is_active' => false]);
    Advertiser::factory()->count(4)->create(['company_id' => $otherCompany->id]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('advertisers.index'));

    $response->assertOk();
    $props = $response->inertiaPage()['props'];

    expect($props['stats']['total'])->toBe(3);
    expect($props['stats']['active'])->toBe(2);
    expect($props['stats']['inactive'])->toBe(1);
    expect($props['advertisers']['total'])->toBe(3);
    expect($props)->not->toHaveKey('companies');
});

test('search and status filters narrow the advertisers list', function () {
    $company = Company::factory()->create();

    Advertiser::factory()->create(['company_id' => $company->id, 'name' => 'Alice Advertiser', 'is_active' => true]);
    Advertiser::factory()->create(['company_id' => $company->id, 'name' => 'Bob Advertiser', 'is_active' => false]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('advertisers.index', ['search' => 'Bob']));
    $advertisers = $response->inertiaPage()['props']['advertisers']['data'];
    expect($advertisers)->toHaveCount(1);
    expect($advertisers[0]['name'])->toBe('Bob Advertiser');

    $response = $this->actingAs($childAdmin)->get(route('advertisers.index', ['status' => 'active']));
    $advertisers = $response->inertiaPage()['props']['advertisers']['data'];
    expect($advertisers)->toHaveCount(1);
    expect($advertisers[0]['name'])->toBe('Alice Advertiser');
});

test('the advertisers page never exposes api_key to the frontend', function () {
    $company = Company::factory()->create();
    Advertiser::factory()->create(['company_id' => $company->id, 'api_key' => 'super-secret-key']);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('advertisers.index'));
    $advertiser = $response->inertiaPage()['props']['advertisers']['data'][0];

    expect($advertiser)->not->toHaveKey('api_key');
});
