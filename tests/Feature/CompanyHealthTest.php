<?php

use App\Models\Company;
use App\Models\JobRun;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Http::fake(['*' => Http::response('', 200)]);
});

test('failure streak counts only the leading consecutive failed runs', function () {
    $company = Company::factory()->create();

    JobRun::factory()->create(['company_id' => $company->id, 'success' => true, 'created_at' => now()->subMinutes(50)]);
    JobRun::factory()->create(['company_id' => $company->id, 'success' => false, 'created_at' => now()->subMinutes(40)]);
    JobRun::factory()->create(['company_id' => $company->id, 'success' => false, 'created_at' => now()->subMinutes(30)]);
    JobRun::factory()->create(['company_id' => $company->id, 'success' => false, 'created_at' => now()->subMinutes(20)]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->get(route('companies.index'));

    $response->assertOk();
    $companies = $response->inertiaPage()['props']['companies']['data'];
    expect($companies[0]['failure_streak'])->toBe(3);
});

test('sync health data does not cause n+1 queries as company count grows', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    Company::factory()->count(2)->create();

    // Warm Spatie's permission cache so both measurements below reflect only
    // this request's own queries, not one-time permission/role lookups.
    $this->actingAs($parentAdmin)->get(route('companies.index'));

    DB::enableQueryLog();
    $this->actingAs($parentAdmin)->get(route('companies.index'));
    $queriesForTwo = count(DB::getQueryLog());
    DB::flushQueryLog();
    DB::disableQueryLog();

    Company::factory()->count(8)->create();

    DB::enableQueryLog();
    $this->actingAs($parentAdmin)->get(route('companies.index'));
    $queriesForTen = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queriesForTen)->toBe($queriesForTwo);
});
