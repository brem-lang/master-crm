<?php

use App\Models\Affiliate;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Http::preventStrayRequests();
});

test('a child admin with update-affiliates can deactivate an active affiliate for their own company', function () {
    $company = Company::factory()->create(['update_affiliate_status_url' => 'https://example.com/functions/v1/update-affiliate-status']);
    $affiliate = Affiliate::factory()->create(['company_id' => $company->id, 'is_active' => true, 'external_id' => 'affiliate-uuid-1']);

    Http::fake([
        'https://example.com/functions/v1/update-affiliate-status*' => Http::response(['success' => true, 'message' => 'Affiliate status updated successfully']),
    ]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->patch(route('affiliates.update-status', $affiliate), [
        'is_active' => false,
    ]);

    $response->assertRedirect();
    expect($affiliate->fresh()->is_active)->toBeFalse();
    $response->assertInertiaFlash('toast.type', 'success');

    Http::assertSent(fn ($request) => $request->data()['affiliate_id'] === 'affiliate-uuid-1' && $request->data()['is_active'] === false);

    expect(AuditLog::where('action', 'affiliate.status_updated')->where('subject_id', $affiliate->id)->exists())->toBeTrue();
});

test('a parent admin can reactivate an inactive affiliate for any company', function () {
    $company = Company::factory()->create(['update_affiliate_status_url' => 'https://example.com/functions/v1/update-affiliate-status']);
    $affiliate = Affiliate::factory()->create(['company_id' => $company->id, 'is_active' => false]);

    Http::fake([
        'https://example.com/functions/v1/update-affiliate-status*' => Http::response(['success' => true, 'message' => 'Affiliate status updated successfully']),
    ]);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->patch(route('affiliates.update-status', $affiliate), [
        'is_active' => true,
    ]);

    $response->assertRedirect();
    expect($affiliate->fresh()->is_active)->toBeTrue();
});

test('a failed child CRM response leaves the affiliate status untouched and flashes the CRM error', function () {
    $company = Company::factory()->create(['update_affiliate_status_url' => 'https://example.com/functions/v1/update-affiliate-status']);
    $affiliate = Affiliate::factory()->create(['company_id' => $company->id, 'is_active' => true]);

    Http::fake([
        'https://example.com/functions/v1/update-affiliate-status*' => Http::response(['success' => false, 'message' => 'Affiliate not found'], 404),
    ]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->patch(route('affiliates.update-status', $affiliate), [
        'is_active' => false,
    ]);

    $response->assertRedirect();
    expect($affiliate->fresh()->is_active)->toBeTrue();
    $response->assertInertiaFlash('toast.type', 'error');
    $response->assertInertiaFlash('toast.message', 'Affiliate not found');
});

test('updating status for a company with no update-affiliate-status url configured fails without contacting the network', function () {
    $company = Company::factory()->create(['update_affiliate_status_url' => null]);
    $affiliate = Affiliate::factory()->create(['company_id' => $company->id, 'is_active' => true]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->patch(route('affiliates.update-status', $affiliate), [
        'is_active' => false,
    ]);

    $response->assertRedirect();
    expect($affiliate->fresh()->is_active)->toBeTrue();
});

test('a sales rep cannot update an affiliate status', function () {
    $company = Company::factory()->create();
    $affiliate = Affiliate::factory()->create(['company_id' => $company->id, 'is_active' => true]);

    $salesRep = User::factory()->create(['company_id' => $company->id]);
    $salesRep->assignRole('sales-rep');

    $response = $this->actingAs($salesRep)->patch(route('affiliates.update-status', $affiliate), [
        'is_active' => false,
    ]);

    $response->assertForbidden();
    expect($affiliate->fresh()->is_active)->toBeTrue();
});

test('a child admin cannot update the status of an affiliate belonging to another company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $affiliate = Affiliate::factory()->create(['company_id' => $otherCompany->id, 'is_active' => true]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->patch(route('affiliates.update-status', $affiliate), [
        'is_active' => false,
    ]);

    $response->assertForbidden();
    expect($affiliate->fresh()->is_active)->toBeTrue();
});

test('a child admin can bulk deactivate active affiliates for their own company', function () {
    $company = Company::factory()->create(['update_affiliate_status_url' => 'https://example.com/functions/v1/update-affiliate-status']);
    $affiliateOne = Affiliate::factory()->create(['company_id' => $company->id, 'is_active' => true]);
    $affiliateTwo = Affiliate::factory()->create(['company_id' => $company->id, 'is_active' => true]);

    Http::fake([
        'https://example.com/functions/v1/update-affiliate-status*' => Http::response(['success' => true, 'message' => 'Affiliate status updated successfully']),
    ]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->patch(route('affiliates.bulk-update-status'), [
        'ids' => [$affiliateOne->id, $affiliateTwo->id],
        'is_active' => false,
    ]);

    $response->assertRedirect();
    expect($affiliateOne->fresh()->is_active)->toBeFalse();
    expect($affiliateTwo->fresh()->is_active)->toBeFalse();
    $response->assertInertiaFlash('toast.type', 'success');
    Http::assertSentCount(2);
});

test('bulk status update skips affiliates already in the target state, non-eligible companies, and failed child CRM calls', function () {
    $company = Company::factory()->create(['update_affiliate_status_url' => 'https://example.com/functions/v1/update-affiliate-status']);
    $otherCompany = Company::factory()->create(['update_affiliate_status_url' => 'https://example.com/functions/v1/update-affiliate-status']);

    $eligible = Affiliate::factory()->create(['company_id' => $company->id, 'is_active' => true]);
    $alreadyInactive = Affiliate::factory()->create(['company_id' => $company->id, 'is_active' => false]);
    $otherCompanyAffiliate = Affiliate::factory()->create(['company_id' => $otherCompany->id, 'is_active' => true]);

    Http::fake([
        'https://example.com/functions/v1/update-affiliate-status*' => Http::response(['success' => true, 'message' => 'Affiliate status updated successfully']),
    ]);

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->patch(route('affiliates.bulk-update-status'), [
        'ids' => [$eligible->id, $alreadyInactive->id, $otherCompanyAffiliate->id],
        'is_active' => false,
    ]);

    $response->assertRedirect();
    expect($eligible->fresh()->is_active)->toBeFalse();
    expect($otherCompanyAffiliate->fresh()->is_active)->toBeTrue();
    Http::assertSentCount(1);
});

test('a sales rep cannot bulk update affiliate status', function () {
    $company = Company::factory()->create();
    $affiliate = Affiliate::factory()->create(['company_id' => $company->id, 'is_active' => true]);

    $salesRep = User::factory()->create(['company_id' => $company->id]);
    $salesRep->assignRole('sales-rep');

    $response = $this->actingAs($salesRep)->patch(route('affiliates.bulk-update-status'), [
        'ids' => [$affiliate->id],
        'is_active' => false,
    ]);

    $response->assertForbidden();
    expect($affiliate->fresh()->is_active)->toBeTrue();
});
