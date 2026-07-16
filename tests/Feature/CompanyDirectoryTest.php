<?php

use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('a parent admin can view the company directory', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->get(route('directory.index'));

    $response->assertOk();
});

test('a child admin cannot view the company directory', function () {
    $company = Company::factory()->create();
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('directory.index'));

    $response->assertForbidden();
});

test('a sales rep cannot view the company directory', function () {
    $company = Company::factory()->create();
    $salesRep = User::factory()->create(['company_id' => $company->id]);
    $salesRep->assignRole('sales-rep');

    $response = $this->actingAs($salesRep)->get(route('directory.index'));

    $response->assertForbidden();
});

test('only active companies appear in the directory', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $active = Company::factory()->create(['name' => 'Active Co', 'is_active' => true]);
    Company::factory()->create(['name' => 'Inactive Co', 'is_active' => false]);

    $response = $this->actingAs($parentAdmin)->get(route('directory.index'));

    $page = $response->inertiaPage()['props'];

    expect($page['companies'])->toHaveCount(1);
    expect($page['companies'][0]['id'])->toBe($active->id);
});

test('directory can be filtered by search', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    Company::factory()->create(['name' => 'Acme Inc', 'is_active' => true]);
    Company::factory()->create(['name' => 'Widgets Co', 'is_active' => true]);

    $response = $this->actingAs($parentAdmin)->get(route('directory.index', ['search' => 'Acme']));

    $page = $response->inertiaPage()['props'];

    expect($page['companies'])->toHaveCount(1);
    expect($page['companies'][0]['name'])->toBe('Acme Inc');
});

test('directory reports website reachability per company', function () {
    Http::fake([
        'https://example.com*' => Http::response('', 200),
        'https://example.org*' => Http::failedConnection('Could not connect'),
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $online = Company::factory()->create(['name' => 'Online Co', 'website' => 'https://example.com']);
    $offline = Company::factory()->create(['name' => 'Offline Co', 'website' => 'https://example.org']);
    $noWebsite = Company::factory()->create(['name' => 'No Website Co', 'website' => null]);

    $response = $this->actingAs($parentAdmin)->get(route('directory.index'));

    $page = $response->inertiaPage()['props'];
    $statuses = collect($page['companies'])->keyBy('id');

    expect($statuses[$online->id]['website_status'])->toBe('online');
    expect($statuses[$offline->id]['website_status'])->toBe('offline');
    expect($statuses[$noWebsite->id]['website_status'])->toBeNull();
});
