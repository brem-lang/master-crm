<?php

use App\Models\Company;
use App\Services\WebsiteHealthChecker;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('a company without a website has no status', function () {
    $company = Company::factory()->make(['id' => 1, 'website' => null]);

    $statuses = (new WebsiteHealthChecker)->statusFor(collect([$company]));

    expect($statuses)->not->toHaveKey(1);
});

test('a reachable website is marked online', function () {
    // Uses a real, publicly-resolvable hostname because the SSRF guard performs
    // an actual DNS lookup independent of Http::fake().
    Http::fake(['https://example.com*' => Http::response('', 200)]);

    $company = Company::factory()->make(['id' => 2, 'website' => 'https://example.com']);

    $statuses = (new WebsiteHealthChecker)->statusFor(collect([$company]));

    expect($statuses[2])->toBe('online');
});

test('a website that fails to connect is marked offline', function () {
    Http::fake(['https://example.org*' => Http::failedConnection('Could not connect')]);

    $company = Company::factory()->make(['id' => 3, 'website' => 'https://example.org']);

    $statuses = (new WebsiteHealthChecker)->statusFor(collect([$company]));

    expect($statuses[3])->toBe('offline');
});

test('a website resolving to a private ip is rejected without making a request', function () {
    Http::fake();

    $company = Company::factory()->make(['id' => 4, 'website' => 'http://127.0.0.1']);

    $statuses = (new WebsiteHealthChecker)->statusFor(collect([$company]));

    expect($statuses[4])->toBe('offline');
    Http::assertNothingSent();
});
