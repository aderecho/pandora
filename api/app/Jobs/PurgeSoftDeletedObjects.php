<?php

namespace App\Jobs;

use App\Models\StoredObject;
use App\Services\Storage\StorageManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Deleting an object already removes its live bytes immediately (see
 * ObjectService::delete); what this purges, after config('fileserver.
 * retention_days'), is any remaining version-history archives plus the
 * stored_objects/object_versions rows themselves — implementing the
 * configurable soft-delete/retention window.
 */
class PurgeSoftDeletedObjects implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(StorageManager $storage): void
    {
        $cutoff = now()->subDays((int) config('fileserver.retention_days'));

        StoredObject::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->with(['bucket', 'versions'])
            ->chunkById(100, function ($objects) use ($storage) {
                foreach ($objects as $object) {
                    if ($object->bucket !== null) {
                        $driver = $storage->driverFor($object->bucket);

                        foreach ($object->versions as $version) {
                            $driver->delete($object->bucket->storage_path, $version->storage_relative_path);
                        }
                    }

                    $object->versions()->delete();
                    $object->forceDelete();
                }
            });
    }
}
