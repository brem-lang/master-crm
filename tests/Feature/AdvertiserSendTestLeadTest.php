<?php

use App\Models\Advertiser;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Http::preventStrayRequests();
});

function testLeadPayload(array $overrides = []): array
{
    return [...[
        'firstname' => 'John',
        'lastname' => 'Doe',
        'email' => 'test.john.doe@example.com',
        'mobile' => '15551234567',
        'country_code' => 'US',
        'country' => 'United States',
        'ip_address' => '203.0.113.1',
    ], ...$overrides];
}

test('a successful send-test-lead response is relayed as-is', function () {
    $company = Company::factory()->create(['send_test_lead_url' => 'https://example.com/functions/v1/send-test-lead']);
    $advertiser = Advertiser::factory()->create(['company_id' => $company->id, 'external_id' => 'aff-uuid-1']);

    Http::fake([
        'https://example.com/functions/v1/send-test-lead*' => Http::response([
            'success' => true,
            'message' => 'Test lead sent and saved successfully',
            'test_mode' => true,
            'advertiser_name' => $advertiser->name,
            'advertiser_response' => '...',
            'lead_id' => 'lead-uuid-1',
        ]),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('parent-admin');

    $response = $this->actingAs($admin)->postJson(route('advertisers.send-test-lead', $advertiser), testLeadPayload());

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'message' => 'Test lead sent and saved successfully',
        'test_mode' => true,
        'lead_id' => 'lead-uuid-1',
    ]);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $body['advertiser_id'] === 'aff-uuid-1'
            && $body['email'] === 'test.john.doe@example.com'
            && $request->hasHeader('Api-Key');
    });
});

test('a validation error from the child CRM is relayed with its status and message', function () {
    $company = Company::factory()->create(['send_test_lead_url' => 'https://example.com/functions/v1/send-test-lead']);
    $advertiser = Advertiser::factory()->create(['company_id' => $company->id]);

    Http::fake([
        'https://example.com/functions/v1/send-test-lead*' => Http::response([
            'success' => false,
            'message' => 'Missing required field: mobile',
        ], 400),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('parent-admin');

    $response = $this->actingAs($admin)->postJson(route('advertisers.send-test-lead', $advertiser), testLeadPayload());

    $response->assertStatus(400);
    $response->assertJson(['success' => false, 'message' => 'Missing required field: mobile']);
});

test('an invalid api key (401) from the child CRM is relayed', function () {
    $company = Company::factory()->create(['send_test_lead_url' => 'https://example.com/functions/v1/send-test-lead']);
    $advertiser = Advertiser::factory()->create(['company_id' => $company->id]);

    Http::fake([
        'https://example.com/functions/v1/send-test-lead*' => Http::response([
            'success' => false,
            'message' => 'Invalid API key',
        ], 401),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('parent-admin');

    $response = $this->actingAs($admin)->postJson(route('advertisers.send-test-lead', $advertiser), testLeadPayload());

    $response->assertStatus(401);
    $response->assertJson(['success' => false, 'message' => 'Invalid API key']);
});

test('an unknown or inactive advertiser (404) from the child CRM is relayed', function () {
    $company = Company::factory()->create(['send_test_lead_url' => 'https://example.com/functions/v1/send-test-lead']);
    $advertiser = Advertiser::factory()->create(['company_id' => $company->id]);

    Http::fake([
        'https://example.com/functions/v1/send-test-lead*' => Http::response([
            'success' => false,
            'message' => 'Advertiser not found or inactive',
        ], 404),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('parent-admin');

    $response = $this->actingAs($admin)->postJson(route('advertisers.send-test-lead', $advertiser), testLeadPayload());

    $response->assertStatus(404);
    $response->assertJson(['success' => false, 'message' => 'Advertiser not found or inactive']);
});

test('a missing send_test_lead_url configuration produces a friendly error without any request', function () {
    Http::fake();

    $company = Company::factory()->create(['send_test_lead_url' => null]);
    $advertiser = Advertiser::factory()->create(['company_id' => $company->id]);

    $admin = User::factory()->create();
    $admin->assignRole('parent-admin');

    $response = $this->actingAs($admin)->postJson(route('advertisers.send-test-lead', $advertiser), testLeadPayload());

    $response->assertStatus(422);
    expect($response->json('success'))->toBeFalse();
    Http::assertNothingSent();
});

test('an unsafe send_test_lead_url never triggers a real request', function () {
    Http::fake();

    $company = Company::factory()->create(['send_test_lead_url' => 'http://127.0.0.1/send-test-lead']);
    $advertiser = Advertiser::factory()->create(['company_id' => $company->id]);

    $admin = User::factory()->create();
    $admin->assignRole('parent-admin');

    $response = $this->actingAs($admin)->postJson(route('advertisers.send-test-lead', $advertiser), testLeadPayload());

    $response->assertStatus(502);
    expect($response->json('success'))->toBeFalse();
    Http::assertNothingSent();
});

test('missing required fields fail our own validation before any request is made', function () {
    Http::fake();

    $company = Company::factory()->create(['send_test_lead_url' => 'https://example.com/functions/v1/send-test-lead']);
    $advertiser = Advertiser::factory()->create(['company_id' => $company->id]);

    $admin = User::factory()->create();
    $admin->assignRole('parent-admin');

    $response = $this->actingAs($admin)->postJson(route('advertisers.send-test-lead', $advertiser), []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email', 'mobile', 'country_code', 'ip_address']);
    Http::assertNothingSent();
});

test('a child admin cannot send a test lead for another company advertiser', function () {
    $ownCompany = Company::factory()->create();
    $otherCompany = Company::factory()->create(['send_test_lead_url' => 'https://example.com/functions/v1/send-test-lead']);
    $advertiser = Advertiser::factory()->create(['company_id' => $otherCompany->id]);

    $childAdmin = User::factory()->create(['company_id' => $ownCompany->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->postJson(route('advertisers.send-test-lead', $advertiser), testLeadPayload());

    $response->assertForbidden();
});

test('a sales rep cannot send a test lead at all', function () {
    $company = Company::factory()->create(['send_test_lead_url' => 'https://example.com/functions/v1/send-test-lead']);
    $advertiser = Advertiser::factory()->create(['company_id' => $company->id]);

    $salesRep = User::factory()->create(['company_id' => $company->id]);
    $salesRep->assignRole('sales-rep');

    $response = $this->actingAs($salesRep)->postJson(route('advertisers.send-test-lead', $advertiser), testLeadPayload());

    $response->assertForbidden();
});

test('a user who can view advertisers but lacks send-test-leads is still forbidden', function () {
    Http::fake();

    $company = Company::factory()->create(['send_test_lead_url' => 'https://example.com/functions/v1/send-test-lead']);
    $advertiser = Advertiser::factory()->create(['company_id' => $company->id]);

    // Granted directly (not via a role), so this user can view advertisers but
    // was never given send-test-leads — proves the two are independently checked.
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->givePermissionTo('view-company-customers');

    $response = $this->actingAs($user)->postJson(route('advertisers.send-test-lead', $advertiser), testLeadPayload());

    $response->assertForbidden();
    Http::assertNothingSent();
});
