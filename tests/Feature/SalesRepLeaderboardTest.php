<?php

use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('the leaderboard computes totals, ftd, and conversion rate per rep', function () {
    $company = Company::factory()->create();

    $repA = User::factory()->create(['company_id' => $company->id, 'name' => 'Rep A']);
    $repA->assignRole('sales-rep');

    $repB = User::factory()->create(['company_id' => $company->id, 'name' => 'Rep B']);
    $repB->assignRole('sales-rep');

    Lead::factory()->count(4)->create(['company_id' => $company->id, 'assigned_to' => $repA->id, 'is_ftd' => false]);
    Lead::factory()->create(['company_id' => $company->id, 'assigned_to' => $repA->id, 'is_ftd' => true]);

    // Rep B has no leads at all — should still appear, zeroed out.
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('leads.leaderboard'));

    $response->assertOk();
    $leaderboard = collect($response->inertiaPage()['props']['leaderboard']);

    $repARow = $leaderboard->firstWhere('id', $repA->id);
    expect($repARow['total'])->toBe(5);
    expect($repARow['ftd'])->toBe(1);
    expect((float) $repARow['conversion_rate'])->toBe(20.0);

    $repBRow = $leaderboard->firstWhere('id', $repB->id);
    expect($repBRow['total'])->toBe(0);
    expect($repBRow['ftd'])->toBe(0);
    expect((float) $repBRow['conversion_rate'])->toBe(0.0);
});

test('a sales rep cannot view the leaderboard', function () {
    $company = Company::factory()->create();
    $salesRep = User::factory()->create(['company_id' => $company->id]);
    $salesRep->assignRole('sales-rep');

    $response = $this->actingAs($salesRep)->get(route('leads.leaderboard'));

    $response->assertForbidden();
});

test('a parent admin without a company cannot view the leaderboard', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->get(route('leads.leaderboard'));

    $response->assertForbidden();
});
