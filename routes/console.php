<?php

use App\Jobs\PullAllCompaniesLeadsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new PullAllCompaniesLeadsJob)->everyFiveMinutes()->withoutOverlapping();

// Schedule::job(new PullAllCompaniesLeadsJob)->everyMinute()->withoutOverlapping();
