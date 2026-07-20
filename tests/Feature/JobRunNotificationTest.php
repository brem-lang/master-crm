<?php

use App\Jobs\PullCompanyLeadsJob;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('a manual pull notifies every parent admin and no one else', function () {
    Http::fake(['*' => Http::response(['success' => true, 'total_leads' => 0], 200)]);

    $company = Company::factory()->create(['api_url' => 'https://example.com/functions/v1/get-leads']);

    $parentAdminA = User::factory()->create();
    $parentAdminA->assignRole('parent-admin');

    $parentAdminB = User::factory()->create();
    $parentAdminB->assignRole('parent-admin');

    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $this->actingAs($parentAdminA)->post(route('companies.pull-data', $company));

    expect($parentAdminA->fresh()->notifications()->count())->toBe(1);
    expect($parentAdminB->fresh()->notifications()->count())->toBe(1);
    expect($childAdmin->fresh()->notifications()->count())->toBe(0);
});

test('a scheduled job dispatch also notifies parent admins', function () {
    Http::fake(['*' => Http::response(['success' => true, 'total_leads' => 0], 200)]);

    $company = Company::factory()->create(['api_url' => 'https://example.com/functions/v1/get-leads']);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    PullCompanyLeadsJob::dispatchSync($company);

    expect($parentAdmin->fresh()->notifications()->count())->toBe(1);
});

test('the shared unread_count prop reflects pending notifications', function () {
    Http::fake(['*' => Http::response(['success' => true, 'total_leads' => 0], 200)]);

    $company = Company::factory()->create(['api_url' => 'https://example.com/functions/v1/get-leads']);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    $response = $this->actingAs($parentAdmin)->get(route('dashboard'));

    expect($response->inertiaPage()['props']['notifications']['unread_count'])->toBe(1);
});

test('marking a notification as read updates only that one', function () {
    Http::fake(['*' => Http::response(['success' => true, 'total_leads' => 0], 200)]);

    $company = Company::factory()->create(['api_url' => 'https://example.com/functions/v1/get-leads']);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));
    $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    $notifications = $parentAdmin->fresh()->notifications;
    expect($notifications)->toHaveCount(2);

    $this->actingAs($parentAdmin)->post(route('notifications.read', $notifications->first()->id))
        ->assertOk();

    expect($parentAdmin->fresh()->unreadNotifications()->count())->toBe(1);
    expect($notifications->first()->fresh()->read_at)->not->toBeNull();
});

test('a user cannot mark another users notification as read', function () {
    Http::fake(['*' => Http::response(['success' => true, 'total_leads' => 0], 200)]);

    $company = Company::factory()->create(['api_url' => 'https://example.com/functions/v1/get-leads']);

    $parentAdminA = User::factory()->create();
    $parentAdminA->assignRole('parent-admin');

    $parentAdminB = User::factory()->create();
    $parentAdminB->assignRole('parent-admin');

    $this->actingAs($parentAdminA)->post(route('companies.pull-data', $company));

    $notification = $parentAdminA->fresh()->notifications->first();

    $this->actingAs($parentAdminB)->post(route('notifications.read', $notification->id))
        ->assertForbidden();

    expect($notification->fresh()->read_at)->toBeNull();
});

test('mark all as read zeroes the unread count', function () {
    Http::fake(['*' => Http::response(['success' => true, 'total_leads' => 0], 200)]);

    $company = Company::factory()->create(['api_url' => 'https://example.com/functions/v1/get-leads']);

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));
    $this->actingAs($parentAdmin)->post(route('companies.pull-data', $company));

    $this->actingAs($parentAdmin)->post(route('notifications.read-all'))->assertOk();

    expect($parentAdmin->fresh()->unreadNotifications()->count())->toBe(0);
});

test('clearing all notifications deletes only the acting users own notifications', function () {
    Http::fake(['*' => Http::response(['success' => true, 'total_leads' => 0], 200)]);

    $company = Company::factory()->create(['api_url' => 'https://example.com/functions/v1/get-leads']);

    $parentAdminA = User::factory()->create();
    $parentAdminA->assignRole('parent-admin');

    $parentAdminB = User::factory()->create();
    $parentAdminB->assignRole('parent-admin');

    $this->actingAs($parentAdminA)->post(route('companies.pull-data', $company));
    $this->actingAs($parentAdminA)->post(route('companies.pull-data', $company));

    expect($parentAdminA->fresh()->notifications()->count())->toBe(2);
    expect($parentAdminB->fresh()->notifications()->count())->toBe(2);

    $this->actingAs($parentAdminA)->delete(route('notifications.clear'))->assertOk();

    expect($parentAdminA->fresh()->notifications()->count())->toBe(0);
    expect($parentAdminB->fresh()->notifications()->count())->toBe(2);
});
