<?php

use App\Jobs\PurgeExpiredTokens;
use App\Jobs\PurgeSoftDeletedObjects;
use App\Jobs\RecalculateBucketUsage;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new PurgeExpiredTokens)->hourly();
Schedule::job(new PurgeSoftDeletedObjects)->daily();
Schedule::call(fn () => RecalculateBucketUsage::dispatchForAllBuckets())->everyFifteenMinutes();
