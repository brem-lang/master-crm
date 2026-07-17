<?php

use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('a child admin sees analytics scoped to only their own company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    Lead::factory()->count(2)->create([
        'company_id' => $company->id,
        'status' => 'rejected',
        'is_ftd' => false,
        'lead_created_at' => now(),
    ]);
    Lead::factory()->count(1)->create([
        'company_id' => $company->id,
        'status' => 'contacted',
        'is_ftd' => true,
        'lead_created_at' => now(),
        'meta' => ['ftd_released' => true],
    ]);
    Lead::factory()->count(5)->create([
        'company_id' => $otherCompany->id,
        'status' => 'rejected',
        'lead_created_at' => now(),
    ]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('dashboard', ['range' => 'today']));

    $response->assertOk();
    $analytics = $response->inertiaPage()['props']['analytics'];

    expect($analytics)->not->toBeNull();
    expect($analytics['stats']['total'])->toBe(3);
    expect($analytics['stats']['rejected'])->toBe(2);
    expect($analytics['stats']['ftd'])->toBe(1);
    expect($analytics['stats']['conversionRate'])->toBe(33.33);
    expect($analytics['stats']['rejectionRate'])->toBe(66.67);
});

test('a parent admin sees aggregated analytics across all companies', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    Lead::factory()->count(2)->create(['company_id' => $companyA->id, 'lead_created_at' => now()]);
    Lead::factory()->count(3)->create(['company_id' => $companyB->id, 'lead_created_at' => now()]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->get(route('dashboard', ['range' => 'today']));

    $response->assertOk();
    $analytics = $response->inertiaPage()['props']['analytics'];

    expect($analytics)->not->toBeNull();
    expect($analytics['stats']['total'])->toBe(5);
});

test('a sales rep still gets the placeholder dashboard, not analytics', function () {
    $company = Company::factory()->create();
    Lead::factory()->count(3)->create(['company_id' => $company->id]);

    $salesRep = User::factory()->create(['company_id' => $company->id]);
    $salesRep->assignRole('sales-rep');

    $response = $this->actingAs($salesRep)->get(route('dashboard'));

    $response->assertOk();
    expect($response->inertiaPage()['props']['analytics'])->toBeNull();
});

test('pending ftds counts leads that are ftd but not yet released', function () {
    $company = Company::factory()->create();

    Lead::factory()->create([
        'company_id' => $company->id,
        'is_ftd' => true,
        'lead_created_at' => now(),
        'meta' => ['ftd_released' => false],
    ]);
    Lead::factory()->create([
        'company_id' => $company->id,
        'is_ftd' => true,
        'lead_created_at' => now(),
        'meta' => ['ftd_released' => true],
    ]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('dashboard', ['range' => 'today']));

    $analytics = $response->inertiaPage()['props']['analytics'];
    expect($analytics['stats']['pendingFtd'])->toBe(1);
});

test('top advertisers are tallied from lead_distributions in meta', function () {
    $company = Company::factory()->create();

    Lead::factory()->create([
        'company_id' => $company->id,
        'is_ftd' => true,
        'lead_created_at' => now(),
        'meta' => [
            'lead_distributions' => [
                ['status' => 'sent', 'advertisers' => ['name' => 'Acme Ads']],
            ],
        ],
    ]);
    Lead::factory()->create([
        'company_id' => $company->id,
        'is_ftd' => false,
        'lead_created_at' => now(),
        'meta' => [
            'lead_distributions' => [
                ['status' => 'sent', 'advertisers' => ['name' => 'Acme Ads']],
            ],
        ],
    ]);
    Lead::factory()->create([
        'company_id' => $company->id,
        'is_ftd' => false,
        'lead_created_at' => now(),
        'meta' => [
            'lead_distributions' => [
                ['status' => 'failed', 'advertisers' => ['name' => 'Other Ads']],
            ],
        ],
    ]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('dashboard', ['range' => 'today']));

    $analytics = $response->inertiaPage()['props']['analytics'];

    expect($analytics['stats']['sent'])->toBe(2);
    expect($analytics['stats']['failed'])->toBe(1);

    $acme = collect($analytics['topAdvertisers'])->firstWhere('name', 'Acme Ads');
    expect($acme['sent'])->toBe(2);
    expect($acme['ftd'])->toBe(1);
});

test('the advertiser filter narrows every metric consistently', function () {
    $company = Company::factory()->create();

    Lead::factory()->create([
        'company_id' => $company->id,
        'lead_created_at' => now(),
        'meta' => ['lead_distributions' => [['status' => 'sent', 'advertisers' => ['name' => 'Acme Ads']]]],
    ]);
    Lead::factory()->create([
        'company_id' => $company->id,
        'lead_created_at' => now(),
        'meta' => ['lead_distributions' => [['status' => 'sent', 'advertisers' => ['name' => 'Other Ads']]]],
    ]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('dashboard', ['range' => 'today', 'advertiser' => 'Acme Ads']));

    $analytics = $response->inertiaPage()['props']['analytics'];
    expect($analytics['stats']['total'])->toBe(1);
    expect($analytics['topAdvertisers'])->toHaveCount(1);
    expect($analytics['topAdvertisers'][0]['name'])->toBe('Acme Ads');
});

test('date range presets narrow results correctly', function () {
    $company = Company::factory()->create();

    Lead::factory()->create(['company_id' => $company->id, 'lead_created_at' => now()]);
    Lead::factory()->create(['company_id' => $company->id, 'lead_created_at' => now()->subDays(10)]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $today = $this->actingAs($childAdmin)->get(route('dashboard', ['range' => 'today']));
    expect($today->inertiaPage()['props']['analytics']['stats']['total'])->toBe(1);

    $all = $this->actingAs($childAdmin)->get(route('dashboard', ['range' => 'all']));
    expect($all->inertiaPage()['props']['analytics']['stats']['total'])->toBe(2);
});
