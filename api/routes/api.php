<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BucketController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ObjectController;
use App\Http\Controllers\Api\V1\PresignedUrlController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Versioned under /api/v1. auth.api accepts either a Bearer API token or an
| authenticated Sanctum SPA session; scope:<permission> then enforces
| bucket/object-level authorization via AccessControlService.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/health', HealthController::class)->name('health');

    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:auth')
        ->name('auth.login');

    Route::post('/auth/logout', [AuthController::class, 'logout'])
        ->middleware('auth.api')
        ->name('auth.logout');

    // Public, signature-verified — no bearer token or session required.
    Route::get('/public/objects/download', [PresignedUrlController::class, 'download'])->name('public.download');
    Route::put('/public/objects/upload', [PresignedUrlController::class, 'upload'])->name('public.upload');

    Route::middleware('auth.api')->group(function () {
        Route::get('/buckets', [BucketController::class, 'index'])
            ->middleware('scope:bucket:list')->name('buckets.index');
        Route::post('/buckets', [BucketController::class, 'store'])
            ->middleware('scope:bucket:write')->name('buckets.store');
        Route::get('/buckets/{bucket}', [BucketController::class, 'show'])
            ->middleware('scope:bucket:read')->name('buckets.show');
        Route::patch('/buckets/{bucket}', [BucketController::class, 'update'])
            ->middleware('scope:bucket:write')->name('buckets.update');
        Route::delete('/buckets/{bucket}', [BucketController::class, 'destroy'])
            ->middleware('scope:bucket:delete')->name('buckets.destroy');

        Route::get('/buckets/{bucket}/objects', [ObjectController::class, 'index'])
            ->middleware('scope:object:list')->name('objects.index');
        Route::post('/buckets/{bucket}/objects', [ObjectController::class, 'store'])
            ->middleware('scope:object:write')->name('objects.store');
        Route::post('/buckets/{bucket}/presigned-upload', [PresignedUrlController::class, 'presignUpload'])
            ->middleware('scope:object:write')->name('objects.presigned-upload');

        // More specific {key} suffix routes must be registered before the
        // bare GET/DELETE {key} routes below, or the wildcard swallows them.
        Route::get('/buckets/{bucket}/objects/{key}/metadata', [ObjectController::class, 'metadata'])
            ->where('key', '.*')->middleware('scope:object:read')->name('objects.metadata');
        Route::post('/buckets/{bucket}/objects/{key}/presigned-download', [PresignedUrlController::class, 'presignDownload'])
            ->where('key', '.*')->middleware('scope:object:read')->name('objects.presigned-download');

        Route::get('/buckets/{bucket}/objects/{key}', [ObjectController::class, 'show'])
            ->where('key', '.*')->middleware('scope:object:read')->name('objects.show');
        Route::delete('/buckets/{bucket}/objects/{key}', [ObjectController::class, 'destroy'])
            ->where('key', '.*')->middleware('scope:object:delete')->name('objects.destroy');
    });
});
