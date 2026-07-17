<?php

use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Http::preventStrayRequests();
});

function queryParam(Request $request, string $key): ?string
{
    parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

    return $query[$key] ?? null;
}

function leadPayload(string $id, string $createdAt): array
{
    return [
        'id' => $id,
        'request_id' => 'req-'.$id,
        'firstname' => 'John',
        'lastname' => 'Doe',
        'email' => "{$id}@example.com",
        'mobile' => '15551234567',
        'country_code' => 'US',
        'ip_address' => '10.0.0.1',
        'status' => 'new',
        'affiliate_id' => 'aff-1',
        'is_ftd' => false,
        'offer_name' => 'Test Offer',
        'created_at' => $createdAt,
        'affiliates' => ['name' => 'Test Affiliate'],
        'lead_distributions' => [],
    ];
}

test('a first pull persists all leads across multiple pages and stamps synced_to_parent_at', function () {
    $company = Company::factory()->create(['api_url' => 'https://example.com/functions/v1/get-leads']);

    Http::fake([
        'https://example.com/functions/v1/get-leads*' => function ($request) {
            $page = (int) queryParam($request, 'page');

            $pages = [
                0 => ['id-1', '2026-07-01T00:00:00Z'],
                1 => ['id-2', '2026-07-02T00:00:00Z'],
            ];

            [$id, $createdAt] = $pages[$page];

            return Http::response([
                'success' => true,
                'total' => 2,
                'all_leads_count' => 2,
                'page' => $page,
                'pages' => 2,
                'next_cursor' => $id,
                'next_since' => $createdAt,
                'data' => [leadPayload($id, $createdAt)],
            ]);
        },
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    $response->assertRedirect(route('companies.index'));
    $response->assertInertiaFlash('toast.type', 'success');

    expect(Lead::where('company_id', $company->id)->count())->toBe(2);

    $lead = Lead::where('external_id', 'id-1')->first();
    expect($lead->first_name)->toBe('John');
    expect($lead->affiliate_name)->toBe('Test Affiliate');
    expect($lead->meta['affiliate_id'])->toBe('aff-1');
    expect($lead->synced_to_parent_at)->not->toBeNull();

    $company->refresh();
    expect($company->last_synced_since)->not->toBeNull();
    expect($company->last_synced_cursor)->toBe('id-2');
});

test('re-pulling when all_leads_count matches the local count makes exactly one request', function () {
    $company = Company::factory()->create(['api_url' => 'https://example.com/functions/v1/get-leads']);
    Lead::factory()->count(2)->create(['company_id' => $company->id, 'synced_to_parent_at' => now()]);

    Http::fake([
        'https://example.com/functions/v1/get-leads*' => Http::response([
            'success' => true,
            'total' => 2,
            'all_leads_count' => 2,
            'page' => 0,
            'pages' => 1,
            'next_cursor' => null,
            'next_since' => null,
            'data' => [],
        ]),
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    $response->assertRedirect(route('companies.index'));
    $response->assertInertiaFlash('toast.type', 'success');

    Http::assertSentCount(1);
    expect(Lead::where('company_id', $company->id)->count())->toBe(2);
});

test('a stale cursor is ignored and a full re-pull happens when local leads were deleted', function () {
    $company = Company::factory()->create([
        'api_url' => 'https://example.com/functions/v1/get-leads',
        'last_synced_since' => now()->subDay(),
        'last_synced_cursor' => 'some-old-cursor',
    ]);
    // No local leads — e.g. they were deleted — even though the company still
    // remembers a cursor from a previous successful sync.

    Http::fake([
        'https://example.com/functions/v1/get-leads*' => function ($request) {
            expect(queryParam($request, 'since'))->toBeNull();

            return Http::response([
                'success' => true,
                'total' => 2,
                'all_leads_count' => 2,
                'page' => 0,
                'pages' => 1,
                'next_cursor' => 'id-2',
                'next_since' => '2026-07-02T00:00:00Z',
                'data' => [
                    leadPayload('id-1', '2026-07-01T00:00:00Z'),
                    leadPayload('id-2', '2026-07-02T00:00:00Z'),
                ],
            ]);
        },
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    $response->assertRedirect(route('companies.index'));
    $response->assertInertiaFlash('toast.type', 'success');

    expect(Lead::where('company_id', $company->id)->count())->toBe(2);
});

test('a failure on a later page preserves earlier pages and reports gracefully, not a 500', function () {
    $company = Company::factory()->create(['api_url' => 'https://example.com/functions/v1/get-leads']);

    Http::fake([
        'https://example.com/functions/v1/get-leads*' => function ($request) {
            $page = (int) queryParam($request, 'page');

            if ($page === 1) {
                return Http::response(null, 500);
            }

            return Http::response([
                'success' => true,
                'total' => 2,
                'all_leads_count' => 2,
                'page' => $page,
                'pages' => 2,
                'next_cursor' => 'id-1',
                'next_since' => '2026-07-01T00:00:00Z',
                'data' => [leadPayload('id-1', '2026-07-01T00:00:00Z')],
            ]);
        },
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    // No 500 — the controller still gets a normal redirect with a friendly error toast.
    $response->assertRedirect(route('companies.index'));
    $response->assertInertiaFlash('toast.type', 'error');

    // Page 0's lead was still saved before page 1 failed.
    expect(Lead::where('company_id', $company->id)->count())->toBe(1);
});

test('a lead item with no id is skipped instead of aborting the whole sync', function () {
    $company = Company::factory()->create(['api_url' => 'https://example.com/functions/v1/get-leads']);

    Http::fake([
        'https://example.com/functions/v1/get-leads*' => Http::response([
            'success' => true,
            'total' => 2,
            'all_leads_count' => 2,
            'page' => 0,
            'pages' => 1,
            'next_cursor' => 'id-1',
            'next_since' => '2026-07-01T00:00:00Z',
            'data' => [
                leadPayload('id-1', '2026-07-01T00:00:00Z'),
                [...leadPayload('id-2', '2026-07-01T00:00:00Z'), 'id' => null],
            ],
        ]),
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    $response->assertRedirect(route('companies.index'));
    $response->assertInertiaFlash('toast.type', 'success');

    expect(Lead::where('company_id', $company->id)->count())->toBe(1);
    expect(Lead::where('external_id', 'id-1')->exists())->toBeTrue();
});

test('an unsafe api url never triggers a real request', function () {
    Http::fake();

    $company = Company::factory()->create(['api_url' => 'http://127.0.0.1']);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    $response->assertRedirect(route('companies.index'));
    $response->assertInertiaFlash('toast.type', 'error');
    Http::assertNothingSent();
});

test('a connection failure produces a friendly error toast', function () {
    Http::fake([
        'https://example.com/functions/v1/get-leads*' => Http::failedConnection('Could not connect'),
    ]);

    $company = Company::factory()->create(['api_url' => 'https://example.com/functions/v1/get-leads']);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    $response->assertRedirect(route('companies.index'));
    $response->assertInertiaFlash('toast.type', 'error');
});

test('a non-2xx response produces a friendly error toast', function () {
    Http::fake([
        'https://example.com/functions/v1/get-leads*' => Http::response(null, 401),
    ]);

    $company = Company::factory()->create(['api_url' => 'https://example.com/functions/v1/get-leads']);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    $response->assertRedirect(route('companies.index'));
    $response->assertInertiaFlash('toast.type', 'error');
});

test('the request preserves an existing query string on api_url and uses the Api-Key header', function () {
    $company = Company::factory()->create([
        'api_url' => 'https://example.com/functions/v1/get-all-leads?includeRejected=1',
        'api_key' => 'super-secret-key',
    ]);

    Http::fake([
        'https://example.com/functions/v1/get-all-leads*' => Http::response([
            'success' => true,
            'total' => 0,
            'all_leads_count' => 0,
            'page' => 0,
            'pages' => 1,
            'next_cursor' => null,
            'next_since' => null,
            'data' => [],
        ]),
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    Http::assertSent(function ($request) {
        return queryParam($request, 'includeRejected') === '1'
            && queryParam($request, 'page') === '0'
            && $request->hasHeader('Api-Key', 'super-secret-key')
            && ! $request->hasHeader('apikey');
    });
});

test('a child admin cannot pull leads for another company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->post(route('companies.pull-data', $otherCompany));

    $response->assertForbidden();
});
