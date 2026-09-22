<?php

namespace App\Services\Storage;

use App\Models\Bucket;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the correct StorageDriverInterface implementation for a given
 * bucket. Only 'local' is implemented today; adding 's3' or 'minio' later
 * means writing a new driver class and registering it here — no change to
 * any controller, service, or CLI code that depends on the interface.
 */
class StorageManager
{
    public function __construct(private readonly Container $container)
    {
    }

    public function driver(string $name = 'local'): StorageDriverInterface
    {
        return match ($name) {
            'local' => $this->container->make(LocalStorageDriver::class),
            's3', 'minio' => throw new \InvalidArgumentException(
                "Storage driver [{$name}] is not implemented yet. Implement StorageDriverInterface ".
                'and register it in StorageManager::driver() to add support.'
            ),
            default => throw new \InvalidArgumentException("Unknown storage driver [{$name}]."),
        };
    }

    public function driverFor(Bucket $bucket): StorageDriverInterface
    {
        return $this->driver($bucket->driver);
    }
}
