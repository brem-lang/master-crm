<?php

use App\Models\Company;
use App\Models\DistributionRule;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('a child admin can view the distribution rules page', function () {
    $company = Company::factory()->create();
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('distribution-rules.index'));

    $response->assertOk();
});

test('a parent admin can view the distribution rules page', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->get(route('distribution-rules.index'));

    $response->assertOk();
});

test('a sales rep cannot view the distribution rules page', function () {
    $company = Company::factory()->create();
    $salesRep = User::factory()->create(['company_id' => $company->id]);
    $salesRep->assignRole('sales-rep');

    $response = $this->actingAs($salesRep)->get(route('distribution-rules.index'));

    $response->assertForbidden();
});

test('a child admin only sees distribution rules scoped to their own company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    DistributionRule::factory()->count(2)->create(['company_id' => $company->id, 'is_active' => true]);
    DistributionRule::factory()->create(['company_id' => $company->id, 'is_active' => false]);
    DistributionRule::factory()->count(5)->create(['company_id' => $otherCompany->id]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('distribution-rules.index'));

    $response->assertOk();
    $props = $response->inertiaPage()['props'];

    expect($props['stats']['total'])->toBe(3);
    expect($props['stats']['active'])->toBe(2);
    expect($props['stats']['inactive'])->toBe(1);
    expect($props['rules']['total'])->toBe(3);
    expect($props)->not->toHaveKey('companies');
});

test('a parent admin sees distribution rules across all companies with a company filter list', function () {
    $companyA = Company::factory()->create(['name' => 'Company A']);
    $companyB = Company::factory()->create(['name' => 'Company B']);

    DistributionRule::factory()->count(2)->create(['company_id' => $companyA->id]);
    DistributionRule::factory()->count(3)->create(['company_id' => $companyB->id]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->get(route('distribution-rules.index'));

    $response->assertOk();
    $props = $response->inertiaPage()['props'];

    expect($props['stats']['total'])->toBe(5);
    expect($props['companies'])->toHaveCount(2);
});

test('the status and priority_type filters narrow the distribution rules list', function () {
    $company = Company::factory()->create();

    DistributionRule::factory()->create(['company_id' => $company->id, 'is_active' => true, 'priority_type' => 'primary']);
    DistributionRule::factory()->create(['company_id' => $company->id, 'is_active' => false, 'priority_type' => 'fallback']);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('distribution-rules.index', ['status' => 'active']));
    expect($response->inertiaPage()['props']['rules']['data'])->toHaveCount(1);

    $response = $this->actingAs($childAdmin)->get(route('distribution-rules.index', ['priority_type' => 'fallback']));
    expect($response->inertiaPage()['props']['rules']['data'])->toHaveCount(1);
});

test('leads_count is a best-effort match on affiliate, advertiser, and country', function () {
    $company = Company::factory()->create();

    $rule = DistributionRule::factory()->create([
        'company_id' => $company->id,
        'affiliate_id' => 'aff-1',
        'advertiser_id' => 'adv-1',
        'country_code' => 'US',
    ]);

    // Matches on every dimension — counted.
    Lead::factory()->create([
        'company_id' => $company->id,
        'country_code' => 'US',
        'status' => 'new',
        'meta' => [
            'affiliate_id' => 'aff-1',
            'lead_distributions' => [['status' => 'sent', 'advertiser_id' => 'adv-1']],
        ],
    ]);

    // Wrong country — not counted.
    Lead::factory()->create([
        'company_id' => $company->id,
        'country_code' => 'CA',
        'meta' => [
            'affiliate_id' => 'aff-1',
            'lead_distributions' => [['status' => 'sent', 'advertiser_id' => 'adv-1']],
        ],
    ]);

    // Wrong affiliate — not counted.
    Lead::factory()->create([
        'company_id' => $company->id,
        'country_code' => 'US',
        'meta' => [
            'affiliate_id' => 'aff-2',
            'lead_distributions' => [['status' => 'sent', 'advertiser_id' => 'adv-1']],
        ],
    ]);

    // Never distributed to the rule's advertiser — not counted.
    Lead::factory()->create([
        'company_id' => $company->id,
        'country_code' => 'US',
        'meta' => [
            'affiliate_id' => 'aff-1',
            'lead_distributions' => [['status' => 'sent', 'advertiser_id' => 'adv-2']],
        ],
    ]);

    // Attempted this advertiser but it failed and fell through to another one
    // that succeeded — the failed attempt must not count toward adv-1.
    Lead::factory()->create([
        'company_id' => $company->id,
        'country_code' => 'US',
        'meta' => [
            'affiliate_id' => 'aff-1',
            'lead_distributions' => [
                ['status' => 'failed', 'advertiser_id' => 'adv-1'],
                ['status' => 'sent', 'advertiser_id' => 'adv-2'],
            ],
        ],
    ]);

    // Different company entirely — not counted.
    Lead::factory()->create([
        'country_code' => 'US',
        'meta' => [
            'affiliate_id' => 'aff-1',
            'lead_distributions' => [['status' => 'sent', 'advertiser_id' => 'adv-1']],
        ],
    ]);

    // Otherwise matches, but rejected — not counted.
    Lead::factory()->create([
        'company_id' => $company->id,
        'country_code' => 'US',
        'status' => 'rejected',
        'meta' => [
            'affiliate_id' => 'aff-1',
            'lead_distributions' => [['status' => 'sent', 'advertiser_id' => 'adv-1']],
        ],
    ]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('distribution-rules.index'));

    $found = collect($response->inertiaPage()['props']['rules']['data'])
        ->firstWhere('id', $rule->id);

    expect($found['leads_count'])->toBe(1);
});

test('a matching lead with no status set is still counted', function () {
    $company = Company::factory()->create();

    $rule = DistributionRule::factory()->create([
        'company_id' => $company->id,
        'affiliate_id' => 'aff-1',
        'advertiser_id' => 'adv-1',
        'country_code' => 'US',
    ]);

    Lead::factory()->create([
        'company_id' => $company->id,
        'country_code' => 'US',
        'status' => null,
        'meta' => [
            'affiliate_id' => 'aff-1',
            'lead_distributions' => [['status' => 'sent', 'advertiser_id' => 'adv-1']],
        ],
    ]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('distribution-rules.index'));

    $found = collect($response->inertiaPage()['props']['rules']['data'])
        ->firstWhere('id', $rule->id);

    expect($found['leads_count'])->toBe(1);
});

test('a rule with a null affiliate or country matches leads on any value for that dimension', function () {
    $company = Company::factory()->create();

    $rule = DistributionRule::factory()->create([
        'company_id' => $company->id,
        'affiliate_id' => null,
        'advertiser_id' => 'adv-1',
        'country_code' => null,
    ]);

    Lead::factory()->create([
        'company_id' => $company->id,
        'country_code' => 'US',
        'status' => 'new',
        'meta' => [
            'affiliate_id' => 'anything',
            'lead_distributions' => [['status' => 'sent', 'advertiser_id' => 'adv-1']],
        ],
    ]);
    Lead::factory()->create([
        'company_id' => $company->id,
        'country_code' => 'FR',
        'status' => 'new',
        'meta' => [
            'affiliate_id' => 'anything-else',
            'lead_distributions' => [['status' => 'sent', 'advertiser_id' => 'adv-1']],
        ],
    ]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('distribution-rules.index'));

    $found = collect($response->inertiaPage()['props']['rules']['data'])
        ->firstWhere('id', $rule->id);

    expect($found['leads_count'])->toBe(2);
});

test('multiple rules sharing the same affiliate reuse one candidate query but count independently', function () {
    $company = Company::factory()->create();

    $ruleA = DistributionRule::factory()->create([
        'company_id' => $company->id,
        'affiliate_id' => 'aff-1',
        'advertiser_id' => 'adv-1',
        'country_code' => 'US',
    ]);
    $ruleB = DistributionRule::factory()->create([
        'company_id' => $company->id,
        'affiliate_id' => 'aff-1',
        'advertiser_id' => 'adv-2',
        'country_code' => 'US',
    ]);

    Lead::factory()->create([
        'company_id' => $company->id,
        'country_code' => 'US',
        'status' => 'new',
        'meta' => ['affiliate_id' => 'aff-1', 'lead_distributions' => [['status' => 'sent', 'advertiser_id' => 'adv-1']]],
    ]);
    Lead::factory()->count(2)->create([
        'company_id' => $company->id,
        'country_code' => 'US',
        'status' => 'new',
        'meta' => ['affiliate_id' => 'aff-1', 'lead_distributions' => [['status' => 'sent', 'advertiser_id' => 'adv-2']]],
    ]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('distribution-rules.index'));
    $data = collect($response->inertiaPage()['props']['rules']['data']);

    expect($data->firstWhere('id', $ruleA->id)['leads_count'])->toBe(1);
    expect($data->firstWhere('id', $ruleB->id)['leads_count'])->toBe(2);
});
