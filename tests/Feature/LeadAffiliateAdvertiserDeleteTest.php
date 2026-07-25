<?php

use App\Models\Advertiser;
use App\Models\Affiliate;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('parent admin can delete a lead and an audit log is recorded', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $lead = Lead::factory()->create();

    $response = $this->actingAs($parentAdmin)->delete(route('leads.destroy', $lead));

    $response->assertRedirect();
    expect(Lead::find($lead->id))->toBeNull();
    expect(AuditLog::where('action', 'lead.deleted')->where('subject_id', $lead->id)->exists())->toBeTrue();
});

test('child admin cannot delete a lead without delete-leads permission', function () {
    $company = Company::factory()->create();
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $lead = Lead::factory()->create(['company_id' => $company->id]);

    $response = $this->actingAs($childAdmin)->delete(route('leads.destroy', $lead));

    $response->assertForbidden();
    expect(Lead::find($lead->id))->not->toBeNull();
});

test('parent admin can delete an affiliate', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $affiliate = Affiliate::factory()->create();

    $response = $this->actingAs($parentAdmin)->delete(route('affiliates.destroy', $affiliate));

    $response->assertRedirect();
    expect(Affiliate::find($affiliate->id))->toBeNull();
});

test('sales rep cannot delete an affiliate', function () {
    $company = Company::factory()->create();
    $salesRep = User::factory()->create(['company_id' => $company->id]);
    $salesRep->assignRole('sales-rep');

    $affiliate = Affiliate::factory()->create(['company_id' => $company->id]);

    $response = $this->actingAs($salesRep)->delete(route('affiliates.destroy', $affiliate));

    $response->assertForbidden();
    expect(Affiliate::find($affiliate->id))->not->toBeNull();
});

test('parent admin can delete an advertiser', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $advertiser = Advertiser::factory()->create();

    $response = $this->actingAs($parentAdmin)->delete(route('advertisers.destroy', $advertiser));

    $response->assertRedirect();
    expect(Advertiser::find($advertiser->id))->toBeNull();
});

test('sales rep cannot delete an advertiser', function () {
    $company = Company::factory()->create();
    $salesRep = User::factory()->create(['company_id' => $company->id]);
    $salesRep->assignRole('sales-rep');

    $advertiser = Advertiser::factory()->create(['company_id' => $company->id]);

    $response = $this->actingAs($salesRep)->delete(route('advertisers.destroy', $advertiser));

    $response->assertForbidden();
    expect(Advertiser::find($advertiser->id))->not->toBeNull();
});

test('parent admin can delete a rejected lead and an audit log is recorded', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $lead = Lead::factory()->create(['status' => 'rejected']);

    $response = $this->actingAs($parentAdmin)->delete(route('leads.rejected.destroy', $lead));

    $response->assertRedirect();
    expect(Lead::find($lead->id))->toBeNull();
    expect(AuditLog::where('action', 'lead.deleted')->where('subject_id', $lead->id)->exists())->toBeTrue();
});

test('parent admin cannot delete a non-rejected lead via the rejected-leads endpoint', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $lead = Lead::factory()->create(['status' => 'new']);

    $response = $this->actingAs($parentAdmin)->delete(route('leads.rejected.destroy', $lead));

    $response->assertStatus(422);
    expect(Lead::find($lead->id))->not->toBeNull();
});

test('parent admin without delete-leads still cannot delete rejected leads without delete-rejected-leads', function () {
    $company = Company::factory()->create();
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $lead = Lead::factory()->create(['company_id' => $company->id, 'status' => 'rejected']);

    $response = $this->actingAs($childAdmin)->delete(route('leads.rejected.destroy', $lead));

    $response->assertForbidden();
    expect(Lead::find($lead->id))->not->toBeNull();
});

test('parent admin can bulk delete rejected leads, ignoring non-rejected ids', function () {
    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $rejected = Lead::factory()->count(2)->create(['status' => 'rejected']);
    $notRejected = Lead::factory()->create(['status' => 'new']);

    $response = $this->actingAs($parentAdmin)->delete(route('leads.rejected.bulk-destroy'), [
        'ids' => [...$rejected->pluck('id')->all(), $notRejected->id],
    ]);

    $response->assertRedirect();
    expect(Lead::count())->toBe(1);
    expect(Lead::find($notRejected->id))->not->toBeNull();
});
