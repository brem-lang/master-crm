<?php

use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('creating a company derives every endpoint url from api_url', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->post(route('companies.store'), [
        'name' => 'Acme Inc',
        'api_url' => 'https://acme.example.com/api',
        'api_key' => 'secret-key',
        'is_active' => '1',
    ])->assertRedirect(route('companies.index'));

    $company = Company::where('name', 'Acme Inc')->firstOrFail();

    expect($company->affiliates_url)->toBe('https://acme.example.com/api/get-all-affiliates');
    expect($company->advertisers_url)->toBe('https://acme.example.com/api/get-all-advertisers');
    expect($company->send_test_lead_url)->toBe('https://acme.example.com/api/send-test-lead');
    expect($company->release_ftd_url)->toBe('https://acme.example.com/api/release-ftd');
    expect($company->send_lead_url)->toBe('https://acme.example.com/api/send-lead');
    expect($company->resend_lead_url)->toBe('https://acme.example.com/api/resend-lead');
    expect($company->update_affiliate_status_url)->toBe('https://acme.example.com/api/update-affiliate-status');
    expect($company->update_advertiser_url)->toBe('https://acme.example.com/api/update-advertiser');
    expect($company->update_affiliate_url)->toBe('https://acme.example.com/api/update-affiliate');
    expect($company->get_affiliate_whitelisted_ips_url)->toBe('https://acme.example.com/api/get-affiliate-whitelisted-ips');
    expect($company->update_distribution_rule_url)->toBe('https://acme.example.com/api/update-distribution-rule');
    expect($company->distribution_rules_url)->toBe('https://acme.example.com/api/get-all-distribution-rules');
    expect($company->leads_count_url)->toBe('https://acme.example.com/api/get-leads-count');
    expect($company->affiliate_count_api_url)->toBe('https://acme.example.com/api/get-affiliate-count');
    expect($company->advertiser_count_api_url)->toBe('https://acme.example.com/api/get-advertiser-count');
});

test('a trailing slash on api_url does not produce a double slash in derived endpoints', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->post(route('companies.store'), [
        'name' => 'Acme Inc',
        'api_url' => 'https://acme.example.com/api/',
        'api_key' => 'secret-key',
        'is_active' => '1',
    ]);

    $company = Company::where('name', 'Acme Inc')->firstOrFail();

    expect($company->affiliates_url)->toBe('https://acme.example.com/api/get-all-affiliates');
});

test('editing a company\'s api_url does not touch its already-derived endpoint urls', function () {
    $company = Company::factory()->create([
        'api_url' => 'https://acme.example.com/api',
        'affiliates_url' => 'https://acme.example.com/api/get-all-affiliates',
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->put(route('companies.update', $company), [
        'name' => $company->name,
        'api_url' => 'https://renamed.example.com/api',
        'is_active' => '1',
    ]);

    expect($company->refresh()->api_url)->toBe('https://renamed.example.com/api');
    // Unchanged — updating the base URL never re-derives sibling endpoints.
    expect($company->affiliates_url)->toBe('https://acme.example.com/api/get-all-affiliates');
});

test('the derived endpoint urls remain individually editable after creation', function () {
    $company = Company::factory()->create([
        'api_url' => 'https://acme.example.com/api',
        'affiliates_url' => 'https://acme.example.com/api/get-all-affiliates',
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->put(route('companies.update', $company), [
        'name' => $company->name,
        'api_url' => $company->api_url,
        'affiliates_url' => 'https://acme.example.com/custom/affiliates-endpoint',
        'is_active' => '1',
    ]);

    expect($company->refresh()->affiliates_url)->toBe('https://acme.example.com/custom/affiliates-endpoint');
});
