<?php

use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('a sales rep sees only leads assigned to them', function () {
    $company = Company::factory()->create();

    $rep = User::factory()->create(['company_id' => $company->id]);
    $rep->assignRole('sales-rep');

    $otherRep = User::factory()->create(['company_id' => $company->id]);
    $otherRep->assignRole('sales-rep');

    Lead::factory()->count(3)->create(['company_id' => $company->id, 'assigned_to' => $rep->id]);
    Lead::factory()->count(2)->create(['company_id' => $company->id, 'assigned_to' => $otherRep->id]);
    Lead::factory()->create(['company_id' => $company->id, 'assigned_to' => null]);

    $response = $this->actingAs($rep)->get(route('my-leads.index'));

    $response->assertOk();
    expect($response->inertiaPage()['props']['total'])->toBe(3);
});

test('a sales rep never sees leads assigned to a rep in another company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $rep = User::factory()->create(['company_id' => $companyA->id]);
    $rep->assignRole('sales-rep');

    $otherRep = User::factory()->create(['company_id' => $companyB->id]);
    $otherRep->assignRole('sales-rep');

    Lead::factory()->create(['company_id' => $companyA->id, 'assigned_to' => $rep->id]);
    Lead::factory()->count(4)->create(['company_id' => $companyB->id, 'assigned_to' => $otherRep->id]);

    $response = $this->actingAs($rep)->get(route('my-leads.index'));

    $response->assertOk();
    expect($response->inertiaPage()['props']['total'])->toBe(1);
});

test('a child admin cannot access my-leads', function () {
    $company = Company::factory()->create();
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('my-leads.index'));

    $response->assertForbidden();
});

test('a parent admin cannot access my-leads', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->get(route('my-leads.index'));

    $response->assertForbidden();
});

test('search and status filters narrow the my-leads list', function () {
    $company = Company::factory()->create();
    $rep = User::factory()->create(['company_id' => $company->id]);
    $rep->assignRole('sales-rep');

    Lead::factory()->create(['company_id' => $company->id, 'assigned_to' => $rep->id, 'first_name' => 'Alice', 'status' => 'new']);
    Lead::factory()->create(['company_id' => $company->id, 'assigned_to' => $rep->id, 'first_name' => 'Bob', 'status' => 'converted']);

    $response = $this->actingAs($rep)->get(route('my-leads.index', ['search' => 'Alice']));
    expect($response->inertiaPage()['props']['leads']['total'])->toBe(1);

    $response = $this->actingAs($rep)->get(route('my-leads.index', ['status' => 'converted']));
    expect($response->inertiaPage()['props']['leads']['total'])->toBe(1);
});
