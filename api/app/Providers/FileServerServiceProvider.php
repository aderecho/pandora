<?php

namespace App\Providers;

use App\Models\ApiToken;
use App\Models\Bucket;
use App\Models\StoredObject;
use App\Policies\ApiTokenPolicy;
use App\Policies\BucketPolicy;
use App\Policies\ObjectPolicy;
use App\Services\PresignedUrlService;
use App\Services\Storage\LocalStorageDriver;
use App\Services\Storage\StorageDriverInterface;
use App\Services\Storage\StorageManager;
use App\Services\VirusScan\ClamAvVirusScanner;
use App\Services\VirusScan\NullVirusScanner;
use App\Services\VirusScan\VirusScannerInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class FileServerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LocalStorageDriver::class, function ($app) {
            return new LocalStorageDriver(
                storageRoot: config('fileserver.storage_root'),
                checksums: $app->make(\App\Services\ChecksumService::class),
            );
        });

        // The default StorageDriverInterface binding is 'local' — the only
        // implementation today. Multi-driver-per-bucket resolution goes
        // through StorageManager::driverFor() instead.
        $this->app->bind(StorageDriverInterface::class, LocalStorageDriver::class);

        $this->app->singleton(StorageManager::class);

        $this->app->singleton(PresignedUrlService::class, function ($app) {
            return new PresignedUrlService(appKey: (string) config('app.key'));
        });

        $this->app->singleton(VirusScannerInterface::class, function () {
            $config = config('fileserver.virus_scan');

            if (! $config['enabled']) {
                return new NullVirusScanner;
            }

            return new ClamAvVirusScanner(
                host: $config['clamav_host'],
                port: $config['clamav_port'],
                timeout: $config['clamav_timeout'],
                enabled: true,
            );
        });
    }

    public function boot(): void
    {
        Gate::policy(Bucket::class, BucketPolicy::class);
        Gate::policy(StoredObject::class, ObjectPolicy::class);
        Gate::policy(ApiToken::class, ApiTokenPolicy::class);

        RateLimiter::for('api', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();

            return Limit::perMinute((int) config('fileserver.rate_limit_api'))->by($key);
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute((int) config('fileserver.rate_limit_auth'))->by($request->ip());
        });
    }
}
