<?php

use App\Models\Company;
use App\Models\DistributionRule;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('a rule with no country restriction breaks its leads down per country', function () {
    $company = Company::factory()->create();

    $rule = DistributionRule::factory()->create([
        'company_id' => $company->id,
        'affiliate_id' => 'aff-1',
        'advertiser_id' => 'adv-1',
        'country_code' => null,
    ]);

    Lead::factory()->count(2)->create([
        'company_id' => $company->id,
        'country_code' => 'US',
        'status' => 'new',
        'meta' => ['affiliate_id' => 'aff-1', 'lead_distributions' => [['status' => 'sent', 'advertiser_id' => 'adv-1']]],
    ]);
    Lead::factory()->create([
        'company_id' => $company->id,
        'country_code' => 'FR',
        'status' => 'new',
        'meta' => ['affiliate_id' => 'aff-1', 'lead_distributions' => [['status' => 'sent', 'advertiser_id' => 'adv-1']]],
    ]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->getJson(route('distribution-rules.leads-by-country', $rule));

    $response->assertOk();
    $counts = collect($response->json('counts'))->keyBy('country_code');

    expect($counts['US']['count'])->toBe(2);
    expect($counts['FR']['count'])->toBe(1);
});

test('the breakdown excludes leads that do not match the rule affiliate or advertiser', function () {
    $company = Company::factory()->create();

    $rule = DistributionRule::factory()->create([
        'company_id' => $company->id,
        'affiliate_id' => 'aff-1',
        'advertiser_id' => 'adv-1',
        'country_code' => null,
    ]);

    Lead::factory()->create([
        'company_id' => $company->id,
        'country_code' => 'US',
        'status' => 'new',
        'meta' => ['affiliate_id' => 'aff-1', 'lead_distributions' => [['status' => 'sent', 'advertiser_id' => 'adv-1']]],
    ]);

    // Wrong affiliate.
    Lead::factory()->create([
        'company_id' => $company->id,
        'country_code' => 'US',
        'status' => 'new',
        'meta' => ['affiliate_id' => 'aff-2', 'lead_distributions' => [['status' => 'sent', 'advertiser_id' => 'adv-1']]],
    ]);

    // Never actually sent to this rule's advertiser.
    Lead::factory()->create([
        'company_id' => $company->id,
        'country_code' => 'US',
        'status' => 'new',
        'meta' => ['affiliate_id' => 'aff-1', 'lead_distributions' => [['status' => 'sent', 'advertiser_id' => 'adv-2']]],
    ]);

    // Rejected leads are excluded regardless of match.
    Lead::factory()->create([
        'company_id' => $company->id,
        'country_code' => 'US',
        'status' => 'rejected',
        'meta' => ['affiliate_id' => 'aff-1', 'lead_distributions' => [['status' => 'sent', 'advertiser_id' => 'adv-1']]],
    ]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->getJson(route('distribution-rules.leads-by-country', $rule));

    $response->assertOk();
    $counts = collect($response->json('counts'))->keyBy('country_code');

    expect($counts['US']['count'])->toBe(1);
});

test('a country-scoped rule reports a single row', function () {
    $company = Company::factory()->create();

    $rule = DistributionRule::factory()->create([
        'company_id' => $company->id,
        'affiliate_id' => null,
        'advertiser_id' => null,
        'country_code' => 'ES',
    ]);

    Lead::factory()->count(3)->create([
        'company_id' => $company->id,
        'country_code' => 'ES',
        'status' => 'new',
    ]);
    Lead::factory()->create([
        'company_id' => $company->id,
        'country_code' => 'FR',
        'status' => 'new',
    ]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    // A rule scoped to a single country still counts across every country
    // present in the candidate set — the endpoint doesn't filter on the
    // rule's own country_code, only groups by it — so both rows are present.
    $response = $this->actingAs($childAdmin)->getJson(route('distribution-rules.leads-by-country', $rule));

    $response->assertOk();
    $counts = collect($response->json('counts'))->keyBy('country_code');

    expect($counts['ES']['count'])->toBe(3);
    expect($counts['FR']['count'])->toBe(1);
});

test('a non parent admin cannot view another company\'s leads-by-country breakdown', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    $rule = DistributionRule::factory()->create(['company_id' => $otherCompany->id]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->getJson(route('distribution-rules.leads-by-country', $rule));

    $response->assertForbidden();
});

test('a parent admin can view any company\'s leads-by-country breakdown', function () {
    $company = Company::factory()->create();

    $rule = DistributionRule::factory()->create([
        'company_id' => $company->id,
        'affiliate_id' => null,
        'advertiser_id' => null,
        'country_code' => null,
    ]);

    Lead::factory()->create([
        'company_id' => $company->id,
        'country_code' => 'DE',
        'status' => 'new',
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->getJson(route('distribution-rules.leads-by-country', $rule));

    $response->assertOk();
    expect(collect($response->json('counts'))->keyBy('country_code')['DE']['count'])->toBe(1);
});
