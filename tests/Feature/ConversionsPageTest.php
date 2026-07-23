<?php

use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('a child admin sees only ftd leads scoped to their own company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    Lead::factory()->count(2)->create(['company_id' => $company->id, 'is_ftd' => true]);
    Lead::factory()->count(1)->create(['company_id' => $company->id, 'is_ftd' => false]);
    Lead::factory()->count(5)->create(['company_id' => $otherCompany->id, 'is_ftd' => true]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('leads.conversions'));

    $response->assertOk();
    $props = $response->inertiaPage()['props'];

    expect($props['total'])->toBe(2);
    expect($props['leads']['total'])->toBe(2);
    expect($props)->not->toHaveKey('companies');
});

test('a sales rep cannot view the conversions page', function () {
    $company = Company::factory()->create();
    Lead::factory()->count(3)->create(['company_id' => $company->id, 'is_ftd' => true]);

    $salesRep = User::factory()->create(['company_id' => $company->id]);
    $salesRep->assignRole('sales-rep');

    $response = $this->actingAs($salesRep)->get(route('leads.conversions'));

    $response->assertForbidden();
});

test('the stat cards report total, released, and pending counts correctly', function () {
    $company = Company::factory()->create();

    Lead::factory()->count(3)->create(['company_id' => $company->id, 'is_ftd' => true, 'ftd_released' => true]);
    Lead::factory()->count(2)->create(['company_id' => $company->id, 'is_ftd' => true, 'ftd_released' => false]);
    Lead::factory()->count(4)->create(['company_id' => $company->id, 'is_ftd' => false]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('leads.conversions'));
    $props = $response->inertiaPage()['props'];

    expect($props['total'])->toBe(5);
    expect($props['released'])->toBe(3);
    expect($props['pending'])->toBe(2);
});

test('a parent admin sees ftd leads across all companies with a company filter list', function () {
    $companyA = Company::factory()->create(['name' => 'Company A']);
    $companyB = Company::factory()->create(['name' => 'Company B']);

    Lead::factory()->count(2)->create(['company_id' => $companyA->id, 'is_ftd' => true]);
    Lead::factory()->count(3)->create(['company_id' => $companyB->id, 'is_ftd' => true]);
    Lead::factory()->count(4)->create(['company_id' => $companyB->id, 'is_ftd' => false]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->get(route('leads.conversions'));

    $response->assertOk();
    $props = $response->inertiaPage()['props'];

    expect($props['total'])->toBe(5);
    expect($props['companies'])->toHaveCount(2);
    expect($props['leads']['data'][0]['company']['name'])->not->toBeNull();
});

test('the released filter narrows the conversions list to released ftds only', function () {
    $company = Company::factory()->create();

    Lead::factory()->count(2)->create(['company_id' => $company->id, 'is_ftd' => true, 'ftd_released' => true]);
    Lead::factory()->count(3)->create(['company_id' => $company->id, 'is_ftd' => true, 'ftd_released' => false]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('leads.conversions', ['released' => 'released']));
    $leads = $response->inertiaPage()['props']['leads']['data'];

    expect($leads)->toHaveCount(2);
    expect(collect($leads)->pluck('ftd_released')->unique()->all())->toBe([true]);
});

test('the pending filter narrows the conversions list to unreleased ftds only', function () {
    $company = Company::factory()->create();

    Lead::factory()->count(2)->create(['company_id' => $company->id, 'is_ftd' => true, 'ftd_released' => true]);
    Lead::factory()->count(3)->create(['company_id' => $company->id, 'is_ftd' => true, 'ftd_released' => false]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('leads.conversions', ['released' => 'pending']));
    $leads = $response->inertiaPage()['props']['leads']['data'];

    expect($leads)->toHaveCount(3);
    expect(collect($leads)->pluck('ftd_released')->unique()->all())->toBe([false]);
});

test('search narrows the conversions list', function () {
    $company = Company::factory()->create();

    Lead::factory()->create(['company_id' => $company->id, 'first_name' => 'Alice', 'is_ftd' => true]);
    Lead::factory()->create(['company_id' => $company->id, 'first_name' => 'Bob', 'is_ftd' => true]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('leads.conversions', ['search' => 'Alice']));
    $leads = $response->inertiaPage()['props']['leads']['data'];

    expect($leads)->toHaveCount(1);
    expect($leads[0]['first_name'])->toBe('Alice');
});

test('the shared pendingFtdCount prop is scoped like the conversions page itself', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    Lead::factory()->count(2)->create(['company_id' => $company->id, 'is_ftd' => true, 'ftd_released' => false]);
    Lead::factory()->count(1)->create(['company_id' => $company->id, 'is_ftd' => true, 'ftd_released' => true]);
    Lead::factory()->count(5)->create(['company_id' => $otherCompany->id, 'is_ftd' => true, 'ftd_released' => false]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('dashboard'));
    expect($response->inertiaPage()['props']['pendingFtdCount'])->toBe(2);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->get(route('dashboard'));
    expect($response->inertiaPage()['props']['pendingFtdCount'])->toBe(7);
});

test('the shared pendingFtdCount prop is null for a user without leads access', function () {
    $company = Company::factory()->create();
    Lead::factory()->count(3)->create(['company_id' => $company->id, 'is_ftd' => true, 'ftd_released' => false]);

    $salesRep = User::factory()->create(['company_id' => $company->id]);
    $salesRep->assignRole('sales-rep');

    $response = $this->actingAs($salesRep)->get(route('dashboard'));

    expect($response->inertiaPage()['props']['pendingFtdCount'])->toBeNull();
});

test('a non-ftd lead never appears on the conversions page regardless of ftd_released', function () {
    $company = Company::factory()->create();

    Lead::factory()->create(['company_id' => $company->id, 'first_name' => 'Alice', 'is_ftd' => true, 'ftd_released' => true]);
    Lead::factory()->create(['company_id' => $company->id, 'first_name' => 'Bob', 'is_ftd' => false, 'ftd_released' => true]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('leads.conversions'));
    $names = collect($response->inertiaPage()['props']['leads']['data'])->pluck('first_name')->all();

    expect($names)->toBe(['Alice']);
});

test('a child admin with release-ftd can release a pending ftd for their own company', function () {
    $company = Company::factory()->create(['release_ftd_url' => 'https://example.com/functions/v1/release-ftd']);
    $lead = Lead::factory()->create(['company_id' => $company->id, 'is_ftd' => true, 'ftd_released' => false, 'external_id' => 'lead-uuid-1']);

    Http::fake([
        'https://example.com/functions/v1/release-ftd*' => Http::response(['success' => true, 'message' => 'Released']),
    ]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->patch(route('leads.release-ftd', $lead));

    $response->assertRedirect();
    expect($lead->fresh()->ftd_released)->toBeTrue();

    Http::assertSent(fn ($request) => $request->data()['lead_id'] === 'lead-uuid-1');
});

test('a failed child CRM response leaves the lead pending and flashes the CRM error', function () {
    $company = Company::factory()->create(['release_ftd_url' => 'https://example.com/functions/v1/release-ftd']);
    $lead = Lead::factory()->create(['company_id' => $company->id, 'is_ftd' => true, 'ftd_released' => false]);

    Http::fake([
        'https://example.com/functions/v1/release-ftd*' => Http::response(['success' => false, 'message' => 'Lead not found'], 404),
    ]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->patch(route('leads.release-ftd', $lead));

    $response->assertRedirect();
    expect($lead->fresh()->ftd_released)->toBeFalse();
    $response->assertInertiaFlash('toast.type', 'error');
    $response->assertInertiaFlash('toast.message', 'Lead not found');
});

test('releasing an ftd for a company with no release-ftd url configured fails without contacting the network', function () {
    $company = Company::factory()->create(['release_ftd_url' => null]);
    $lead = Lead::factory()->create(['company_id' => $company->id, 'is_ftd' => true, 'ftd_released' => false]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->patch(route('leads.release-ftd', $lead));

    $response->assertRedirect();
    expect($lead->fresh()->ftd_released)->toBeFalse();
});

test('a sales rep cannot release an ftd', function () {
    $company = Company::factory()->create();
    $lead = Lead::factory()->create(['company_id' => $company->id, 'is_ftd' => true, 'ftd_released' => false]);

    $salesRep = User::factory()->create(['company_id' => $company->id]);
    $salesRep->assignRole('sales-rep');

    $response = $this->actingAs($salesRep)->patch(route('leads.release-ftd', $lead));

    $response->assertForbidden();
    expect($lead->fresh()->ftd_released)->toBeFalse();
});

test('a child admin cannot release an ftd belonging to another company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $lead = Lead::factory()->create(['company_id' => $otherCompany->id, 'is_ftd' => true, 'ftd_released' => false]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->patch(route('leads.release-ftd', $lead));

    $response->assertForbidden();
    expect($lead->fresh()->ftd_released)->toBeFalse();
});

test('a non-ftd lead cannot be released', function () {
    $company = Company::factory()->create();
    $lead = Lead::factory()->create(['company_id' => $company->id, 'is_ftd' => false, 'ftd_released' => false]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->patch(route('leads.release-ftd', $lead));

    $response->assertStatus(422);
});
