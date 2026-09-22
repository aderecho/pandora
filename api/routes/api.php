<?php

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Versioned under /api/v1. Auth, bucket/object, token, and audit-log
| endpoints are added in later phases as their controllers land.
|
*/

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class)->name('api.v1.health');
});
