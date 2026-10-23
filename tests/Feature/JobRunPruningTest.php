<?php

use App\Models\Company;
use App\Models\JobRun;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('job runs older than 30 days are pruned', function () {
    $company = Company::factory()->create();

    $old = JobRun::factory()->create(['company_id' => $company->id, 'created_at' => now()->subDays(31)]);
    $recent = JobRun::factory()->create(['company_id' => $company->id, 'created_at' => now()->subDays(29)]);

    $this->artisan('model:prune', ['--model' => [JobRun::class]])->assertSuccessful();

    expect(JobRun::find($old->id))->toBeNull();
    expect(JobRun::find($recent->id))->not->toBeNull();
});
