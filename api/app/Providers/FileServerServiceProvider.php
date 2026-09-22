<?php

namespace App\Providers;

use App\Services\Storage\LocalStorageDriver;
use App\Services\Storage\StorageDriverInterface;
use App\Services\Storage\StorageManager;
use App\Services\VirusScan\ClamAvVirusScanner;
use App\Services\VirusScan\NullVirusScanner;
use App\Services\VirusScan\VirusScannerInterface;
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
        //
    }
}
