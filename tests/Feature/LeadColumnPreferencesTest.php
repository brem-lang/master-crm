<?php

use App\Models\Company;
use App\Models\LeadColumnPreference;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('a user can save their hidden columns and column order together', function () {
    $company = Company::factory()->create();
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->patch(route('leads.column-preferences.update'), [
        'hidden_columns' => ['comment', 'ip_address'],
        'column_order' => ['email', 'first_name', 'last_name'],
    ]);

    $response->assertRedirect();

    $preference = LeadColumnPreference::where('user_id', $childAdmin->id)->first();
    expect($preference->hidden_columns)->toBe(['comment', 'ip_address']);
    expect($preference->column_order)->toBe(['email', 'first_name', 'last_name']);
});

test('saving hidden columns without a column order leaves the previously saved order untouched', function () {
    $company = Company::factory()->create();
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    LeadColumnPreference::create([
        'user_id' => $childAdmin->id,
        'hidden_columns' => [],
        'column_order' => ['email', 'first_name', 'last_name'],
    ]);

    $response = $this->actingAs($childAdmin)->patch(route('leads.column-preferences.update'), [
        'hidden_columns' => ['comment'],
    ]);

    $response->assertRedirect();

    $preference = LeadColumnPreference::where('user_id', $childAdmin->id)->first();
    expect($preference->hidden_columns)->toBe(['comment']);
    expect($preference->column_order)->toBe(['email', 'first_name', 'last_name']);
});

test('unknown or duplicate column order keys are dropped instead of rejected', function () {
    $company = Company::factory()->create();
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->patch(route('leads.column-preferences.update'), [
        'hidden_columns' => [],
        'column_order' => ['email', 'email', 'first_name'],
    ]);

    $response->assertRedirect();

    $preference = LeadColumnPreference::where('user_id', $childAdmin->id)->first();
    expect($preference->column_order)->toBe(['email', 'first_name']);
});

test('an invalid column key in hidden_columns is rejected', function () {
    $company = Company::factory()->create();
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->patch(route('leads.column-preferences.update'), [
        'hidden_columns' => ['not_a_real_column'],
    ]);

    $response->assertInvalid(['hidden_columns.0']);
});

test('the leads index returns null column preferences when none are saved yet', function () {
    $company = Company::factory()->create();
    $childAdmin = User::factory()->create(['company_id' => $company->id]);
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('leads.index'));

    $response->assertOk();
    $props = $response->inertiaPage()['props'];

    expect($props['hiddenColumns'])->toBeNull();
    expect($props['columnOrder'])->toBeNull();
});
