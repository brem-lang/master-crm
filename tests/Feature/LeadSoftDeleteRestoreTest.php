<?php

use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('deleting a lead soft-deletes it rather than removing the row', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $lead = Lead::factory()->create();

    $response = $this->actingAs($parentAdmin)->delete(route('leads.destroy', $lead));

    $response->assertRedirect();
    expect(Lead::count())->toBe(0);
    expect(Lead::withTrashed()->count())->toBe(1);
    expect(Lead::withTrashed()->find($lead->id)->deleted_at)->not->toBeNull();
});

test('parent admin can restore a soft-deleted lead', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $lead = Lead::factory()->create();
    $lead->delete();

    $response = $this->actingAs($parentAdmin)->patch(route('leads.restore', $lead->id));

    $response->assertRedirect();
    expect(Lead::find($lead->id))->not->toBeNull();
    expect(Lead::find($lead->id)->deleted_at)->toBeNull();
});

test('parent admin can bulk restore soft-deleted leads', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $leads = Lead::factory()->count(3)->create();
    $leads->each->delete();

    $response = $this->actingAs($parentAdmin)->patch(route('leads.bulk-restore'), [
        'ids' => $leads->pluck('id')->all(),
    ]);

    $response->assertRedirect();
    expect(Lead::count())->toBe(3);
});

test('a user without restore-leads cannot restore a lead', function () {
    $company = Company::factory()->create();
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $lead = Lead::factory()->create(['company_id' => $company->id]);
    $lead->delete();

    $response = $this->actingAs($childAdmin)->patch(route('leads.restore', $lead->id));

    $response->assertForbidden();
    expect(Lead::find($lead->id))->toBeNull();
});

test('the leads index only returns trashed leads when ?trashed=1 is requested', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $active = Lead::factory()->count(2)->create(['status' => 'new']);
    $trashed = Lead::factory()->count(3)->create(['status' => 'new']);
    $trashed->each->delete();

    $response = $this->actingAs($parentAdmin)->get(route('leads.index'));
    $response->assertOk();
    expect($response->inertiaPage()['props']['leads']['total'])->toBe(2);

    $response = $this->actingAs($parentAdmin)->get(route('leads.index', ['trashed' => 1]));
    $response->assertOk();
    expect($response->inertiaPage()['props']['leads']['total'])->toBe(3);
    expect($active)->not->toBeEmpty();
});

test('restoring a rejected lead requires it to still be status rejected', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $lead = Lead::factory()->create(['status' => 'new']);
    $lead->delete();

    $response = $this->actingAs($parentAdmin)->patch(route('leads.rejected.restore', $lead->id));

    $response->assertStatus(422);
    expect(Lead::find($lead->id))->toBeNull();
});

test('parent admin can restore a soft-deleted rejected lead', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $lead = Lead::factory()->create(['status' => 'rejected']);
    $lead->delete();

    $response = $this->actingAs($parentAdmin)->patch(route('leads.rejected.restore', $lead->id));

    $response->assertRedirect();
    expect(Lead::find($lead->id))->not->toBeNull();
});

test('parent admin can bulk restore soft-deleted rejected leads', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $leads = Lead::factory()->count(2)->create(['status' => 'rejected']);
    $leads->each->delete();

    $response = $this->actingAs($parentAdmin)->patch(route('leads.rejected.bulk-restore'), [
        'ids' => $leads->pluck('id')->all(),
    ]);

    $response->assertRedirect();
    expect(Lead::count())->toBe(2);
});
