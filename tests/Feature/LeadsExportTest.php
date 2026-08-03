<?php

use App\Exports\LeadsExport;
use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Excel::fake();
    Excel::matchByRegex();
});

test('a child admin can export their company leads to excel, scoped to their own company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    Lead::factory()->count(2)->create(['company_id' => $company->id, 'status' => 'contacted']);
    Lead::factory()->count(3)->create(['company_id' => $otherCompany->id]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('leads.export'));

    $response->assertOk();

    Excel::assertDownloaded('/^leads-.*\.xlsx$/', function (LeadsExport $export) {
        return $export->query()->count() === 2;
    });
});

test('a sales rep cannot export leads', function () {
    $company = Company::factory()->create();
    $salesRep = User::factory()->create(['company_id' => $company->id]);
    $salesRep->assignRole('sales-rep');

    $response = $this->actingAs($salesRep)->get(route('leads.export'));

    $response->assertForbidden();
});

test('export respects the search filter', function () {
    $company = Company::factory()->create();

    Lead::factory()->create(['company_id' => $company->id, 'first_name' => 'Findable', 'status' => 'new']);
    Lead::factory()->create(['company_id' => $company->id, 'first_name' => 'Other', 'status' => 'contacted']);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $this->actingAs($childAdmin)
        ->get(route('leads.export', ['search' => 'Findable']))
        ->assertOk();

    Excel::assertDownloaded('/^leads-.*\.xlsx$/', function (LeadsExport $export) {
        $leads = $export->query()->get();

        return $leads->count() === 1 && $leads->first()->first_name === 'Findable';
    });
});
