<?php

use App\Models\Advertiser;
use App\Models\Affiliate;
use App\Models\Company;
use App\Models\JobRun;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Http::preventStrayRequests();
});

function queryParamValue(Request $request, string $key): ?string
{
    parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

    return $query[$key] ?? null;
}

function affiliatePayload(string $id, array $overrides = []): array
{
    return [...[
        'id' => $id,
        'user_id' => null,
        'name' => 'Test Affiliate',
        'api_key' => 'test-api-key-12345',
        'is_active' => true,
        'created_at' => '2026-06-08T20:02:01.32106+00:00',
        'updated_at' => '2026-06-30T18:34:15.88132+00:00',
        'callback_url' => null,
        'allowed_countries' => null,
        'test_mode' => false,
        'ip_whitelist_required' => true,
        'allowed_ips' => ['192.168.1.1', '192.168.1.2'],
    ], ...$overrides];
}

function advertiserPayload(string $id, array $overrides = []): array
{
    return [...[
        'id' => $id,
        'name' => 'Mock Advertiser',
        'advertiser_type' => 'mock',
        'url' => null,
        'api_key' => null,
        'config' => [],
        'daily_cap' => 999999,
        'hourly_cap' => null,
        'is_active' => true,
        'created_at' => '2026-06-08T19:06:36.653616+00:00',
        'updated_at' => '2026-07-09T21:57:18.210204+00:00',
        'status_endpoint' => null,
        'default_deal_type' => 'cpa',
        'default_crg_base_price' => 0,
        'default_crg_guarantee_percent' => 0,
    ], ...$overrides];
}

test('a first pull persists affiliates and advertisers, hides and encrypts api_key', function () {
    $company = Company::factory()->create([
        'api_url' => 'https://example.com/functions/v1/get-leads',
        'affiliates_url' => 'https://example.com/functions/v1/get-all-affiliates',
        'advertisers_url' => 'https://example.com/functions/v1/get-all-advertisers',
    ]);

    Http::fake([
        'https://example.com/functions/v1/get-leads*' => Http::response([
            'success' => true, 'total' => 0, 'all_leads_count' => 0, 'page' => 0, 'pages' => 1,
            'next_cursor' => null, 'next_since' => null, 'data' => [],
        ]),
        'https://example.com/functions/v1/get-all-affiliates*' => Http::response([
            'success' => true,
            'total' => 1,
            'all_affiliates_count' => 1,
            'page' => 0,
            'pages' => 1,
            'next_cursor' => 'aff-1',
            'next_since' => '2026-06-30T18:34:15.88132+00:00',
            'data' => [affiliatePayload('aff-1')],
        ]),
        'https://example.com/functions/v1/get-all-advertisers*' => Http::response([
            'success' => true,
            'total' => 1,
            'all_advertisers_count' => 1,
            'page' => 0,
            'pages' => 1,
            'next_cursor' => 'adv-1',
            'next_since' => '2026-07-09T21:57:18.210204+00:00',
            'data' => [advertiserPayload('adv-1')],
        ]),
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));
    $response->assertRedirect(route('companies.index'));

    expect(Affiliate::where('company_id', $company->id)->count())->toBe(1);
    expect(Advertiser::where('company_id', $company->id)->count())->toBe(1);

    $affiliate = Affiliate::where('external_id', 'aff-1')->first();
    expect($affiliate->name)->toBe('Test Affiliate');
    expect($affiliate->is_active)->toBeTrue();
    expect($affiliate->meta['allowed_ips'])->toBe(['192.168.1.1', '192.168.1.2']);
    expect($affiliate->getAttributes()['api_key'])->not->toBe('test-api-key-12345');
    expect($affiliate->toArray())->not->toHaveKey('api_key');

    $advertiser = Advertiser::where('external_id', 'adv-1')->first();
    expect($advertiser->name)->toBe('Mock Advertiser');
    expect($advertiser->advertiser_type)->toBe('mock');
    expect($advertiser->daily_cap)->toBe(999999);
    expect($advertiser->default_deal_type)->toBe('cpa');
    expect($advertiser->toArray())->not->toHaveKey('api_key');

    $company->refresh();
    expect($company->affiliates_last_synced_cursor)->toBe('aff-1');
    expect($company->advertisers_last_synced_cursor)->toBe('adv-1');

    expect(JobRun::where('company_id', $company->id)->count())->toBe(3);
});

test('pull data only syncs the directory entities whose url is configured', function () {
    $company = Company::factory()->create([
        'api_url' => 'https://example.com/functions/v1/get-leads',
        'affiliates_url' => 'https://example.com/functions/v1/get-all-affiliates',
        'advertisers_url' => null,
    ]);

    Http::fake([
        'https://example.com/functions/v1/get-leads*' => Http::response([
            'success' => true, 'total' => 0, 'all_leads_count' => 0, 'page' => 0, 'pages' => 1,
            'next_cursor' => null, 'next_since' => null, 'data' => [],
        ]),
        'https://example.com/functions/v1/get-all-affiliates*' => Http::response([
            'success' => true, 'total' => 0, 'all_affiliates_count' => 0, 'page' => 0, 'pages' => 1,
            'next_cursor' => null, 'next_since' => null, 'data' => [],
        ]),
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    expect(JobRun::where('company_id', $company->id)->count())->toBe(2);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'get-all-advertisers'));
});

test('multi-page affiliate sync persists every page', function () {
    $company = Company::factory()->create([
        'api_url' => 'https://example.com/functions/v1/get-leads',
        'affiliates_url' => 'https://example.com/functions/v1/get-all-affiliates',
    ]);

    Http::fake([
        'https://example.com/functions/v1/get-leads*' => Http::response([
            'success' => true, 'total' => 0, 'all_leads_count' => 0, 'page' => 0, 'pages' => 1,
            'next_cursor' => null, 'next_since' => null, 'data' => [],
        ]),
        'https://example.com/functions/v1/get-all-affiliates*' => function ($request) {
            $page = (int) queryParamValue($request, 'page');

            $ids = [0 => 'aff-1', 1 => 'aff-2'];

            return Http::response([
                'success' => true,
                'total' => 2,
                'all_affiliates_count' => 2,
                'page' => $page,
                'pages' => 2,
                'next_cursor' => $ids[$page],
                'next_since' => '2026-06-30T18:34:15.88132+00:00',
                'data' => [affiliatePayload($ids[$page])],
            ]);
        },
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    expect(Affiliate::where('company_id', $company->id)->count())->toBe(2);
});

test('a company with more pages than the per-run cap logs a truncation warning', function () {
    Log::spy();

    $company = Company::factory()->create([
        'api_url' => 'https://example.com/functions/v1/get-leads',
        'affiliates_url' => 'https://example.com/functions/v1/get-all-affiliates',
    ]);

    Http::fake([
        'https://example.com/functions/v1/get-leads*' => Http::response([
            'success' => true, 'total' => 0, 'all_leads_count' => 0, 'page' => 0, 'pages' => 1,
            'next_cursor' => null, 'next_since' => null, 'data' => [],
        ]),
        'https://example.com/functions/v1/get-all-affiliates*' => Http::response([
            'success' => true,
            'total' => 100000,
            'all_affiliates_count' => 100000,
            'page' => 0,
            'pages' => 1000,
            'next_cursor' => null,
            'next_since' => null,
            'data' => [],
        ]),
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($message) => str_contains($message, 'more than the 500 per-run cap'))
        ->once();
});

test('a mid-run failure still persists the resume cursor from pages that already succeeded', function () {
    $company = Company::factory()->create([
        'api_url' => 'https://example.com/functions/v1/get-leads',
        'affiliates_url' => 'https://example.com/functions/v1/get-all-affiliates',
    ]);

    Http::fake([
        'https://example.com/functions/v1/get-leads*' => Http::response([
            'success' => true, 'total' => 0, 'all_leads_count' => 0, 'page' => 0, 'pages' => 1,
            'next_cursor' => null, 'next_since' => null, 'data' => [],
        ]),
        'https://example.com/functions/v1/get-all-affiliates*' => function ($request) {
            if ((int) queryParamValue($request, 'page') === 0) {
                return Http::response([
                    'success' => true,
                    'total' => 2,
                    'all_affiliates_count' => 2,
                    'page' => 0,
                    'pages' => 2,
                    'next_cursor' => 'aff-1',
                    'next_since' => '2026-06-30T18:34:15.88132+00:00',
                    'data' => [affiliatePayload('aff-1')],
                ]);
            }

            return Http::response(null, 500);
        },
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    $company->refresh();
    expect($company->affiliates_last_synced_cursor)->toBe('aff-1');
    expect($company->affiliates_last_synced_since)->not->toBeNull();
    expect(Affiliate::where('company_id', $company->id)->count())->toBe(1);

    $jobRun = JobRun::where('company_id', $company->id)->orderByDesc('id')->first();
    expect($jobRun->success)->toBeFalse();
});

test('re-pulling when all_affiliates_count matches the local count makes exactly one directory request', function () {
    $company = Company::factory()->create([
        'api_url' => 'https://example.com/functions/v1/get-leads',
        'affiliates_url' => 'https://example.com/functions/v1/get-all-affiliates',
    ]);
    Affiliate::factory()->count(2)->create(['company_id' => $company->id]);

    Http::fake([
        'https://example.com/functions/v1/get-leads*' => Http::response([
            'success' => true, 'total' => 0, 'all_leads_count' => 0, 'page' => 0, 'pages' => 1,
            'next_cursor' => null, 'next_since' => null, 'data' => [],
        ]),
        'https://example.com/functions/v1/get-all-affiliates*' => Http::response([
            'success' => true, 'total' => 2, 'all_affiliates_count' => 2, 'page' => 0, 'pages' => 1,
            'next_cursor' => null, 'next_since' => null, 'data' => [],
        ]),
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    Http::assertSentCount(2);
    expect(Affiliate::where('company_id', $company->id)->count())->toBe(2);
});

test('an inclusive since boundary re-fetching a known advertiser is not miscounted as new', function () {
    $company = Company::factory()->create([
        'api_url' => 'https://example.com/functions/v1/get-leads',
        'advertisers_url' => 'https://example.com/functions/v1/get-all-advertisers',
        'advertisers_last_synced_since' => now()->subDay(),
    ]);
    Advertiser::factory()->create(['company_id' => $company->id, 'external_id' => 'adv-existing']);

    Http::fake([
        'https://example.com/functions/v1/get-leads*' => Http::response([
            'success' => true, 'total' => 0, 'all_leads_count' => 0, 'page' => 0, 'pages' => 1,
            'next_cursor' => null, 'next_since' => null, 'data' => [],
        ]),
        'https://example.com/functions/v1/get-all-advertisers*' => Http::response([
            'success' => true,
            'total' => 2,
            'all_advertisers_count' => 2,
            'page' => 0,
            'pages' => 1,
            'next_cursor' => 'adv-new',
            'next_since' => now()->toIso8601String(),
            'data' => [advertiserPayload('adv-existing'), advertiserPayload('adv-new')],
        ]),
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    $jobRun = JobRun::where('company_id', $company->id)->orderByDesc('id')->first();
    expect($jobRun->pulled)->toBe(1);
    expect(Advertiser::where('company_id', $company->id)->count())->toBe(2);
});

test('an unsafe affiliates url never triggers a real request', function () {
    Http::fake();

    $company = Company::factory()->create([
        'api_url' => 'https://example.com/functions/v1/get-leads',
        'affiliates_url' => 'http://127.0.0.1/get-all-affiliates',
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'get-all-affiliates'));

    $jobRun = JobRun::where('company_id', $company->id)->orderByDesc('id')->first();
    expect($jobRun->success)->toBeFalse();
});

test('an unsafe advertisers url never triggers a real request', function () {
    Http::fake();

    $company = Company::factory()->create([
        'api_url' => 'https://example.com/functions/v1/get-leads',
        'advertisers_url' => 'http://169.254.169.254/get-all-advertisers',
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'get-all-advertisers'));

    $jobRun = JobRun::where('company_id', $company->id)->orderByDesc('id')->first();
    expect($jobRun->success)->toBeFalse();
});

test('affiliate_count_api_url is no longer consulted — get-all-affiliates is always called even when the count matches', function () {
    $company = Company::factory()->create([
        'api_url' => 'https://example.com/functions/v1/get-leads',
        'affiliates_url' => 'https://example.com/functions/v1/get-all-affiliates',
        'affiliate_count_api_url' => 'https://example.com/functions/v1/count-affiliates',
    ]);
    Affiliate::factory()->count(2)->create(['company_id' => $company->id]);

    Http::fake([
        'https://example.com/functions/v1/get-leads*' => Http::response([
            'success' => true, 'total' => 0, 'all_leads_count' => 0, 'page' => 0, 'pages' => 1,
            'next_cursor' => null, 'next_since' => null, 'data' => [],
        ]),
        'https://example.com/functions/v1/get-all-affiliates*' => Http::response([
            'success' => true, 'total' => 2, 'all_affiliates_count' => 2, 'page' => 0, 'pages' => 1,
            'next_cursor' => null, 'next_since' => null, 'data' => [],
        ]),
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    // The count endpoint is never hit — a total-count match can't tell an in-place
    // is_active update from "nothing changed", so get-all-affiliates is always checked.
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'count-affiliates'));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'get-all-affiliates'));
    expect(Affiliate::where('company_id', $company->id)->count())->toBe(2);
});

test('an in-place is_active update on an already-known affiliate is pulled even though the total count is unchanged', function () {
    $company = Company::factory()->create([
        'api_url' => 'https://example.com/functions/v1/get-leads',
        'affiliates_url' => 'https://example.com/functions/v1/get-all-affiliates',
        'affiliates_last_synced_since' => now()->subDay(),
    ]);
    Affiliate::factory()->create(['company_id' => $company->id, 'external_id' => 'aff-1', 'is_active' => true]);

    Http::fake([
        'https://example.com/functions/v1/get-leads*' => Http::response([
            'success' => true, 'total' => 0, 'all_leads_count' => 0, 'page' => 0, 'pages' => 1,
            'next_cursor' => null, 'next_since' => null, 'data' => [],
        ]),
        // Same total count as local (1) — the old count-based short-circuit would have
        // stopped here without ever seeing the is_active flip below.
        'https://example.com/functions/v1/get-all-affiliates*' => Http::response([
            'success' => true,
            'total' => 1,
            'all_affiliates_count' => 1,
            'page' => 0,
            'pages' => 1,
            'next_cursor' => 'aff-1',
            'next_since' => now()->toIso8601String(),
            'data' => [affiliatePayload('aff-1', ['is_active' => false])],
        ]),
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    expect(Affiliate::where('external_id', 'aff-1')->first()->is_active)->toBeFalse();
});

test('advertiser_count_api_url is no longer consulted — get-all-advertisers is always called even when the count matches', function () {
    $company = Company::factory()->create([
        'api_url' => 'https://example.com/functions/v1/get-leads',
        'advertisers_url' => 'https://example.com/functions/v1/get-all-advertisers',
        'advertiser_count_api_url' => 'https://example.com/functions/v1/count-advertisers',
    ]);
    Advertiser::factory()->count(2)->create(['company_id' => $company->id]);

    Http::fake([
        'https://example.com/functions/v1/get-leads*' => Http::response([
            'success' => true, 'total' => 0, 'all_leads_count' => 0, 'page' => 0, 'pages' => 1,
            'next_cursor' => null, 'next_since' => null, 'data' => [],
        ]),
        'https://example.com/functions/v1/get-all-advertisers*' => Http::response([
            'success' => true, 'total' => 2, 'all_advertisers_count' => 2, 'page' => 0, 'pages' => 1,
            'next_cursor' => null, 'next_since' => null, 'data' => [],
        ]),
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'count-advertisers'));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'get-all-advertisers'));
    expect(Advertiser::where('company_id', $company->id)->count())->toBe(2);
});

test('an in-place is_active update on an already-known advertiser is pulled even though the total count is unchanged', function () {
    $company = Company::factory()->create([
        'api_url' => 'https://example.com/functions/v1/get-leads',
        'advertisers_url' => 'https://example.com/functions/v1/get-all-advertisers',
        'advertisers_last_synced_since' => now()->subDay(),
    ]);
    Advertiser::factory()->create(['company_id' => $company->id, 'external_id' => 'adv-1', 'is_active' => true]);

    Http::fake([
        'https://example.com/functions/v1/get-leads*' => Http::response([
            'success' => true, 'total' => 0, 'all_leads_count' => 0, 'page' => 0, 'pages' => 1,
            'next_cursor' => null, 'next_since' => null, 'data' => [],
        ]),
        'https://example.com/functions/v1/get-all-advertisers*' => Http::response([
            'success' => true,
            'total' => 1,
            'all_advertisers_count' => 1,
            'page' => 0,
            'pages' => 1,
            'next_cursor' => 'adv-1',
            'next_since' => now()->toIso8601String(),
            'data' => [advertiserPayload('adv-1', ['is_active' => false])],
        ]),
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    expect(Advertiser::where('external_id', 'adv-1')->first()->is_active)->toBeFalse();
});

test('a soft-deleted affiliate from the child CRM is removed locally', function () {
    $company = Company::factory()->create([
        'api_url' => 'https://example.com/functions/v1/get-leads',
        'affiliates_url' => 'https://example.com/functions/v1/get-all-affiliates',
    ]);
    Affiliate::factory()->create(['company_id' => $company->id, 'external_id' => 'aff-deleted']);
    Affiliate::factory()->create(['company_id' => $company->id, 'external_id' => 'aff-kept']);

    Http::fake([
        'https://example.com/functions/v1/get-leads*' => Http::response([
            'success' => true, 'total' => 0, 'all_leads_count' => 0, 'page' => 0, 'pages' => 1,
            'next_cursor' => null, 'next_since' => null, 'data' => [],
        ]),
        'https://example.com/functions/v1/get-all-affiliates*' => Http::response([
            'success' => true,
            'total' => 2,
            'all_affiliates_count' => 2,
            'page' => 0,
            'pages' => 1,
            'next_cursor' => 'aff-kept',
            'next_since' => now()->toIso8601String(),
            'data' => [
                [...affiliatePayload('aff-deleted'), 'deleted_at' => now()->toIso8601String()],
                affiliatePayload('aff-kept'),
            ],
        ]),
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    expect(Affiliate::where('external_id', 'aff-deleted')->exists())->toBeFalse();
    expect(Affiliate::where('external_id', 'aff-kept')->exists())->toBeTrue();

    $jobRun = JobRun::where('company_id', $company->id)->orderByDesc('id')->first();
    expect($jobRun->deleted)->toBe(1);
});

test('a soft-deleted advertiser from the child CRM is removed locally', function () {
    $company = Company::factory()->create([
        'api_url' => 'https://example.com/functions/v1/get-leads',
        'advertisers_url' => 'https://example.com/functions/v1/get-all-advertisers',
    ]);
    Advertiser::factory()->create(['company_id' => $company->id, 'external_id' => 'adv-deleted']);
    Advertiser::factory()->create(['company_id' => $company->id, 'external_id' => 'adv-kept']);

    Http::fake([
        'https://example.com/functions/v1/get-leads*' => Http::response([
            'success' => true, 'total' => 0, 'all_leads_count' => 0, 'page' => 0, 'pages' => 1,
            'next_cursor' => null, 'next_since' => null, 'data' => [],
        ]),
        'https://example.com/functions/v1/get-all-advertisers*' => Http::response([
            'success' => true,
            'total' => 2,
            'all_advertisers_count' => 2,
            'page' => 0,
            'pages' => 1,
            'next_cursor' => 'adv-kept',
            'next_since' => now()->toIso8601String(),
            'data' => [
                [...advertiserPayload('adv-deleted'), 'deleted_at' => now()->toIso8601String()],
                advertiserPayload('adv-kept'),
            ],
        ]),
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    expect(Advertiser::where('external_id', 'adv-deleted')->exists())->toBeFalse();
    expect(Advertiser::where('external_id', 'adv-kept')->exists())->toBeTrue();

    $jobRun = JobRun::where('company_id', $company->id)->orderByDesc('id')->first();
    expect($jobRun->deleted)->toBe(1);
});
