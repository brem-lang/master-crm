<?php

use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('inactive sales reps are excluded from the assignable reps list', function () {
    $company = Company::factory()->create();

    $activeRep = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
    $activeRep->assignRole('sales-rep');

    $inactiveRep = User::factory()->create(['company_id' => $company->id, 'is_active' => false]);
    $inactiveRep->assignRole('sales-rep');

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('leads.index'));

    $response->assertOk();
    $salesRepIds = collect($response->inertiaPage()['props']['salesReps'])->pluck('id');
    expect($salesRepIds)->toContain($activeRep->id);
    expect($salesRepIds)->not->toContain($inactiveRep->id);
});

test('assigning a lead to an inactive sales rep is rejected', function () {
    $company = Company::factory()->create();
    $lead = Lead::factory()->create(['company_id' => $company->id]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $inactiveRep = User::factory()->create(['company_id' => $company->id, 'is_active' => false]);
    $inactiveRep->assignRole('sales-rep');

    $response = $this->actingAs($childAdmin)->patch(route('leads.assign', $lead), [
        'assigned_to' => $inactiveRep->id,
    ]);

    $response->assertSessionHasErrors('assigned_to');
    expect($lead->fresh()->assigned_to)->toBeNull();
});

test('bulk assigning to an inactive sales rep is rejected', function () {
    $company = Company::factory()->create();
    $lead = Lead::factory()->create(['company_id' => $company->id]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $inactiveRep = User::factory()->create(['company_id' => $company->id, 'is_active' => false]);
    $inactiveRep->assignRole('sales-rep');

    $response = $this->actingAs($childAdmin)->patch(route('leads.bulk-assign'), [
        'ids' => [$lead->id],
        'assigned_to' => $inactiveRep->id,
    ]);

    $response->assertSessionHasErrors('assigned_to');
    expect($lead->fresh()->assigned_to)->toBeNull();
});

test('a child admin can assign a lead to a sales rep in their company', function () {
    $company = Company::factory()->create();
    $lead = Lead::factory()->create(['company_id' => $company->id]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $rep = User::factory()->create(['company_id' => $company->id]);
    $rep->assignRole('sales-rep');

    $response = $this->actingAs($childAdmin)->patch(route('leads.assign', $lead), [
        'assigned_to' => $rep->id,
    ]);

    $response->assertRedirect();
    expect($lead->fresh()->assigned_to)->toBe($rep->id);
});

test('a child admin can unassign a lead', function () {
    $company = Company::factory()->create();
    $rep = User::factory()->create(['company_id' => $company->id]);
    $rep->assignRole('sales-rep');
    $lead = Lead::factory()->create(['company_id' => $company->id, 'assigned_to' => $rep->id]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->patch(route('leads.assign', $lead), [
        'assigned_to' => null,
    ]);

    $response->assertRedirect();
    expect($lead->fresh()->assigned_to)->toBeNull();
});

test('a child admin cannot assign a lead from a different company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $lead = Lead::factory()->create(['company_id' => $companyB->id]);

    $childAdmin = User::factory()->create(['company_id' => $companyA->id]);
    $childAdmin->assignRole('child-admin');

    $rep = User::factory()->create(['company_id' => $companyB->id]);
    $rep->assignRole('sales-rep');

    $response = $this->actingAs($childAdmin)->patch(route('leads.assign', $lead), [
        'assigned_to' => $rep->id,
    ]);

    $response->assertForbidden();
});

test('a child admin cannot assign a lead to a user from a different company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $lead = Lead::factory()->create(['company_id' => $company->id]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $otherRep = User::factory()->create(['company_id' => $otherCompany->id]);
    $otherRep->assignRole('sales-rep');

    $response = $this->actingAs($childAdmin)->patch(route('leads.assign', $lead), [
        'assigned_to' => $otherRep->id,
    ]);

    $response->assertSessionHasErrors('assigned_to');
});

test('a child admin can bulk assign multiple leads to a sales rep', function () {
    $company = Company::factory()->create();
    $leads = Lead::factory()->count(3)->create(['company_id' => $company->id]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $rep = User::factory()->create(['company_id' => $company->id]);
    $rep->assignRole('sales-rep');

    $response = $this->actingAs($childAdmin)->patch(route('leads.bulk-assign'), [
        'ids' => $leads->pluck('id')->all(),
        'assigned_to' => $rep->id,
    ]);

    $response->assertRedirect();
    $response->assertInertiaFlash('toast.type', 'success');

    foreach ($leads as $lead) {
        expect($lead->fresh()->assigned_to)->toBe($rep->id);
    }
});

test('a child admin can bulk unassign leads', function () {
    $company = Company::factory()->create();
    $rep = User::factory()->create(['company_id' => $company->id]);
    $rep->assignRole('sales-rep');
    $leads = Lead::factory()->count(2)->create(['company_id' => $company->id, 'assigned_to' => $rep->id]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->patch(route('leads.bulk-assign'), [
        'ids' => $leads->pluck('id')->all(),
        'assigned_to' => null,
    ]);

    $response->assertRedirect();

    foreach ($leads as $lead) {
        expect($lead->fresh()->assigned_to)->toBeNull();
    }
});

test('bulk assign skips leads that belong to a different company than the chosen rep', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $leadA = Lead::factory()->create(['company_id' => $companyA->id]);
    $leadB = Lead::factory()->create(['company_id' => $companyB->id]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $repA = User::factory()->create(['company_id' => $companyA->id]);
    $repA->assignRole('sales-rep');

    $response = $this->actingAs($parentAdmin)->patch(route('leads.bulk-assign'), [
        'ids' => [$leadA->id, $leadB->id],
        'assigned_to' => $repA->id,
    ]);

    $response->assertRedirect();
    expect($leadA->fresh()->assigned_to)->toBe($repA->id);
    expect($leadB->fresh()->assigned_to)->toBeNull();
});

test('a sales rep cannot bulk assign leads', function () {
    $company = Company::factory()->create();
    $lead = Lead::factory()->create(['company_id' => $company->id]);

    $salesRep = User::factory()->create(['company_id' => $company->id]);
    $salesRep->assignRole('sales-rep');

    $response = $this->actingAs($salesRep)->patch(route('leads.bulk-assign'), [
        'ids' => [$lead->id],
        'assigned_to' => $salesRep->id,
    ]);

    $response->assertForbidden();
});

test('a parent admin can assign a lead to a sales rep in that leads company', function () {
    $company = Company::factory()->create();
    $lead = Lead::factory()->create(['company_id' => $company->id]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $rep = User::factory()->create(['company_id' => $company->id]);
    $rep->assignRole('sales-rep');

    $response = $this->actingAs($parentAdmin)->patch(route('leads.assign', $lead), [
        'assigned_to' => $rep->id,
    ]);

    $response->assertRedirect();
    expect($lead->fresh()->assigned_to)->toBe($rep->id);
});

test('a parent admin cannot assign a lead to a rep from a different company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $lead = Lead::factory()->create(['company_id' => $companyA->id]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $repFromOtherCompany = User::factory()->create(['company_id' => $companyB->id]);
    $repFromOtherCompany->assignRole('sales-rep');

    $response = $this->actingAs($parentAdmin)->patch(route('leads.assign', $lead), [
        'assigned_to' => $repFromOtherCompany->id,
    ]);

    $response->assertSessionHasErrors('assigned_to');
});

test('the leads index exposes sales reps across all companies to a parent admin', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $repA = User::factory()->create(['company_id' => $companyA->id]);
    $repA->assignRole('sales-rep');

    $repB = User::factory()->create(['company_id' => $companyB->id]);
    $repB->assignRole('sales-rep');

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->get(route('leads.index'));

    $response->assertOk();
    $salesRepIds = collect($response->inertiaPage()['props']['salesReps'])->pluck('id');
    expect($salesRepIds)->toContain($repA->id, $repB->id);
});

test('a sales rep cannot assign leads', function () {
    $company = Company::factory()->create();
    $lead = Lead::factory()->create(['company_id' => $company->id]);

    $salesRep = User::factory()->create(['company_id' => $company->id]);
    $salesRep->assignRole('sales-rep');

    $response = $this->actingAs($salesRep)->patch(route('leads.assign', $lead), [
        'assigned_to' => $salesRep->id,
    ]);

    $response->assertForbidden();
});
