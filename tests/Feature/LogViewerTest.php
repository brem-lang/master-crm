<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('parent admin can view application logs', function () {
    $path = storage_path('logs/log-viewer-test.log');
    config(['logging.channels.single.path' => $path]);
    file_put_contents($path, "[2026-07-26 10:00:00] local.ERROR: Something broke\n[stacktrace]\n#0 test\n");

    $parentAdmin = User::factory()->create();
    $parentAdmin->assignRole('parent-admin');

    $response = $this->actingAs($parentAdmin)->get(route('logs.index'));

    $response->assertOk();
    $entries = $response->inertiaPage()['props']['entries'];
    expect($entries['total'])->toBe(1);
    expect($entries['data'][0]['message'])->toBe('Something broke');
    expect($entries['data'][0]['level'])->toBe('ERROR');

    unlink($path);
});

test('user without view-system-logs permission cannot view application logs', function () {
    $childAdmin = User::factory()->create();
    $childAdmin->assignRole('child-admin');

    $response = $this->actingAs($childAdmin)->get(route('logs.index'));

    $response->assertForbidden();
});
