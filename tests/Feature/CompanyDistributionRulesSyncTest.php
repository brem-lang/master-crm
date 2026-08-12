<?php

use App\Models\Company;
use App\Models\DistributionRule;
use App\Models\JobRun;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Http::preventStrayRequests();
});

function queryParamValueForDistributionRules(Request $request, string $key): ?string
{
    parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

    return $query[$key] ?? null;
}

function distributionRulePayload(string $id, array $overrides = []): array
{
    return [...[
        'id' => $id,
        'affiliate_id' => 'aff-1',
        'advertiser_id' => 'adv-1',
        'country_code' => 'US',
        'weight' => 100,
        'daily_cap' => 500,
        'hourly_cap' => 50,
        'is_active' => true,
        'priority_type' => 'primary',
        'priority' => 100,
        'start_time' => '08:00',
        'end_time' => '18:00',
        'weekly_schedule' => null,
        'timezone' => 'UTC',
        'created_at' => '2026-06-01T12:00:00.000Z',
        'updated_at' => '2026-08-10T09:00:11.000Z',
        'advertiser_name' => 'Acme Inc',
        'advertiser_is_active' => true,
        'affiliate_name' => 'Acme Traffic Co',
        'affiliate_is_active' => true,
    ], ...$overrides];
}

test('a first pull persists distribution rules and records a job run', function () {
    $company = Company::factory()->create([
        'api_url' => 'https://example.com/functions/v1/get-leads',
        'distribution_rules_url' => 'https://example.com/functions/v1/get-all-distribution-rules',
    ]);

    Http::fake([
        'https://example.com/functions/v1/get-leads*' => Http::response([
            'success' => true, 'total' => 0, 'all_leads_count' => 0, 'page' => 0, 'pages' => 1,
            'next_cursor' => null, 'next_since' => null, 'data' => [],
        ]),
        'https://example.com/functions/v1/get-all-distribution-rules*' => Http::response([
            'success' => true,
            'total' => 1,
            'all_rules_count' => 1,
            'page' => 0,
            'pages' => 1,
            'next_cursor' => 'rule-1',
            'next_since' => '2026-08-10T09:00:11.000Z',
            'data' => [distributionRulePayload('rule-1')],
        ]),
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));
    $response->assertRedirect(route('companies.index'));

    expect(DistributionRule::where('company_id', $company->id)->count())->toBe(1);

    $rule = DistributionRule::where('external_id', 'rule-1')->first();
    expect($rule->affiliate_id)->toBe('aff-1');
    expect($rule->advertiser_id)->toBe('adv-1');
    expect($rule->country_code)->toBe('US');
    expect($rule->priority_type)->toBe('primary');
    expect($rule->is_active)->toBeTrue();
    expect($rule->meta['advertiser_name'])->toBe('Acme Inc');

    $company->refresh();
    expect($company->distribution_rules_last_synced_cursor)->toBe('rule-1');

    expect(JobRun::where('company_id', $company->id)->count())->toBe(2);
});

test('pull data skips distribution rules sync when no url is configured', function () {
    $company = Company::factory()->create([
        'api_url' => 'https://example.com/functions/v1/get-leads',
        'distribution_rules_url' => null,
    ]);

    Http::fake([
        'https://example.com/functions/v1/get-leads*' => Http::response([
            'success' => true, 'total' => 0, 'all_leads_count' => 0, 'page' => 0, 'pages' => 1,
            'next_cursor' => null, 'next_since' => null, 'data' => [],
        ]),
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    expect(JobRun::where('company_id', $company->id)->count())->toBe(1);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'get-all-distribution-rules'));
});

test('multi-page distribution rules sync persists every page', function () {
    $company = Company::factory()->create([
        'api_url' => 'https://example.com/functions/v1/get-leads',
        'distribution_rules_url' => 'https://example.com/functions/v1/get-all-distribution-rules',
    ]);

    Http::fake([
        'https://example.com/functions/v1/get-leads*' => Http::response([
            'success' => true, 'total' => 0, 'all_leads_count' => 0, 'page' => 0, 'pages' => 1,
            'next_cursor' => null, 'next_since' => null, 'data' => [],
        ]),
        'https://example.com/functions/v1/get-all-distribution-rules*' => function ($request) {
            $page = (int) queryParamValueForDistributionRules($request, 'page');

            $ids = [0 => 'rule-1', 1 => 'rule-2'];

            return Http::response([
                'success' => true,
                'total' => 2,
                'all_rules_count' => 2,
                'page' => $page,
                'pages' => 2,
                'next_cursor' => $ids[$page],
                'next_since' => '2026-08-10T09:00:11.000Z',
                'data' => [distributionRulePayload($ids[$page])],
            ]);
        },
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    expect(DistributionRule::where('company_id', $company->id)->count())->toBe(2);
});

test('a soft-deleted distribution rule from the child CRM is removed locally', function () {
    $company = Company::factory()->create([
        'api_url' => 'https://example.com/functions/v1/get-leads',
        'distribution_rules_url' => 'https://example.com/functions/v1/get-all-distribution-rules',
    ]);
    DistributionRule::factory()->create(['company_id' => $company->id, 'external_id' => 'rule-deleted']);
    DistributionRule::factory()->create(['company_id' => $company->id, 'external_id' => 'rule-kept']);

    Http::fake([
        'https://example.com/functions/v1/get-leads*' => Http::response([
            'success' => true, 'total' => 0, 'all_leads_count' => 0, 'page' => 0, 'pages' => 1,
            'next_cursor' => null, 'next_since' => null, 'data' => [],
        ]),
        'https://example.com/functions/v1/get-all-distribution-rules*' => Http::response([
            'success' => true,
            'total' => 2,
            'all_rules_count' => 2,
            'page' => 0,
            'pages' => 1,
            'next_cursor' => 'rule-kept',
            'next_since' => now()->toIso8601String(),
            'data' => [
                [...distributionRulePayload('rule-deleted'), 'deleted_at' => now()->toIso8601String()],
                distributionRulePayload('rule-kept'),
            ],
        ]),
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    expect(DistributionRule::where('external_id', 'rule-deleted')->exists())->toBeFalse();
    expect(DistributionRule::where('external_id', 'rule-kept')->exists())->toBeTrue();
});

test('an unsafe distribution rules url never triggers a real request', function () {
    Http::fake();

    $company = Company::factory()->create([
        'api_url' => 'https://example.com/functions/v1/get-leads',
        'distribution_rules_url' => 'http://127.0.0.1/get-all-distribution-rules',
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'get-all-distribution-rules'));

    $jobRun = JobRun::where('company_id', $company->id)->orderByDesc('id')->first();
    expect($jobRun->success)->toBeFalse();
});
