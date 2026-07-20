<?php

use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('a parent admin finds a matching company, user, and lead', function () {
    $company = Company::factory()->create(['name' => 'Acme Marketing']);
    $matchingUser = User::factory()->create(['name' => 'Jane Acme', 'email' => 'jane@example.com']);
    $lead = Lead::factory()->create(['company_id' => $company->id, 'first_name' => 'Acme', 'last_name' => 'Lead']);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->getJson(route('search.index', ['q' => 'Acme']));

    $response->assertOk();
    $body = $response->json();

    expect(collect($body['companies'])->pluck('id'))->toContain($company->id);
    expect(collect($body['users'])->pluck('id'))->toContain($matchingUser->id);
    expect(collect($body['leads'])->pluck('id'))->toContain($lead->id);
});

test('a query under two characters returns empty results', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->getJson(route('search.index', ['q' => 'a']));

    $response->assertOk();
    expect($response->json())->toBe(['companies' => [], 'users' => [], 'leads' => []]);
});

test('a child admin cannot use global search', function () {
    $company = Company::factory()->create();
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->getJson(route('search.index', ['q' => 'test']));

    $response->assertForbidden();
});

test('view_user query param surfaces the matching user as a prop', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $target = User::factory()->create();
    $target->assignRole('sales-rep');

    $response = $this->actingAs($parentAdmin)->get(route('users.index', ['view_user' => $target->id]));

    $response->assertOk();
    expect($response->inertiaPage()['props']['viewUser']['id'])->toBe($target->id);
});

test('view_lead query param surfaces the matching lead as a prop', function () {
    $company = Company::factory()->create();
    $lead = Lead::factory()->create(['company_id' => $company->id]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->get(route('leads.index', ['view_lead' => $lead->id]));

    $response->assertOk();
    expect($response->inertiaPage()['props']['viewLead']['id'])->toBe($lead->id);
});
