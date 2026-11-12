<?php

use App\Models\AuditLog;
use App\Models\LeadLog;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['services.lead_logs.token' => 'test-token']);
});

test('the api stores a lead log with a valid token', function () {
    $this->withToken('test-token')->postJson('/api/leads-logs', [
        'email' => 'lead@example.com',
        'offer_name' => 'Crypto Pro',
        'ip_address' => '203.0.113.5',
        'data' => ['country' => 'DE', 'phone' => '123'],
    ])->assertCreated()->assertJsonStructure(['data' => ['id', 'created_at']]);

    $log = LeadLog::sole();
    expect($log->email)->toBe('lead@example.com');
    expect($log->offer_name)->toBe('Crypto Pro');
    expect($log->ip_address)->toBe('203.0.113.5');
    expect($log->data)->toBe(['country' => 'DE', 'phone' => '123']);
});

test('the api accepts an empty data array', function () {
    $this->withToken('test-token')->postJson('/api/leads-logs', [
        'email' => 'lead@example.com',
        'offer_name' => 'Crypto Pro',
        'ip_address' => '2001:db8::1',
        'data' => [],
    ])->assertCreated();
});

test('the api rejects a missing or wrong token', function () {
    $payload = ['email' => 'lead@example.com', 'offer_name' => 'Offer', 'data' => []];

    $this->postJson('/api/leads-logs', $payload)->assertUnauthorized();
    $this->withToken('wrong')->postJson('/api/leads-logs', $payload)->assertUnauthorized();

    expect(LeadLog::count())->toBe(0);
});

test('the api fails closed when no token is configured', function () {
    config(['services.lead_logs.token' => null]);

    $this->withToken('')->postJson('/api/leads-logs', [
        'email' => 'lead@example.com', 'offer_name' => 'Offer', 'data' => [],
    ])->assertUnauthorized();
});

test('the api validates the payload', function () {
    $this->withToken('test-token')->postJson('/api/leads-logs', [
        'email' => 'not-an-email',
        'ip_address' => '999.1.1.1',
        'data' => 'string',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'offer_name', 'ip_address', 'data']);
});

test('the api requires ip_address', function () {
    $this->withToken('test-token')->postJson('/api/leads-logs', [
        'email' => 'lead@example.com',
        'offer_name' => 'Crypto Pro',
        'data' => [],
    ])->assertUnprocessable()->assertJsonValidationErrors(['ip_address']);
});

test('parent admins can view and filter the leads logs page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('parent-admin');

    LeadLog::factory()->create(['email' => 'match@example.com', 'offer_name' => 'Alpha']);
    LeadLog::factory()->create(['email' => 'other@example.com', 'offer_name' => 'Beta']);

    $this->actingAs($admin)
        ->get(route('leads-logs.index', ['offer' => 'Alpha']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('leads-logs/index')
            ->has('entries.data', 1)
            ->where('entries.data.0.email', 'match@example.com')
            ->where('offers', ['Alpha', 'Beta']));
});

test('users without manage-companies cannot view the leads logs page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('leads-logs.index'))->assertForbidden();
});

test('parent admins can bulk delete lead logs', function () {
    $admin = User::factory()->create();
    $admin->assignRole('parent-admin');

    [$first, $second, $kept] = LeadLog::factory()->count(3)->create();

    $this->actingAs($admin)
        ->delete(route('leads-logs.bulk-destroy'), ['ids' => [$first->id, $second->id]])
        ->assertRedirect();

    expect(LeadLog::pluck('id')->all())->toBe([$kept->id]);

    $audit = AuditLog::where('action', 'lead_logs.bulk_deleted')->sole();
    expect($audit->actor_id)->toBe($admin->id);
    expect($audit->changes['count'])->toBe(2);
});

test('bulk delete validates ids', function () {
    $admin = User::factory()->create();
    $admin->assignRole('parent-admin');

    $this->actingAs($admin)
        ->delete(route('leads-logs.bulk-destroy'), ['ids' => ['abc']])
        ->assertSessionHasErrors('ids.0');

    $this->actingAs($admin)
        ->delete(route('leads-logs.bulk-destroy'), [])
        ->assertSessionHasErrors('ids');
});

test('users without manage-companies cannot bulk delete lead logs', function () {
    $user = User::factory()->create();
    $log = LeadLog::factory()->create();

    $this->actingAs($user)
        ->delete(route('leads-logs.bulk-destroy'), ['ids' => [$log->id]])
        ->assertForbidden();

    expect(LeadLog::whereKey($log->id)->exists())->toBeTrue();
});
