<?php

namespace App\Jobs;

use App\Enums\ActorType;
use App\Enums\AuditStatus;
use App\Models\StoredObject;
use App\Services\AuditLogService;
use App\Services\Storage\StorageManager;
use App\Services\VirusScan\VirusScannerInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs after an upload has already been accepted and recorded, so uploads
 * never block on ClamAV. NullVirusScanner (the default) makes this a no-op;
 * once ClamAV is enabled, an infected file is quarantined here instead of
 * at request time.
 */
class ScanObjectForViruses implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $bucketId,
        public readonly string $objectKey,
    ) {}

    public function handle(VirusScannerInterface $scanner, StorageManager $storage, AuditLogService $auditLog): void
    {
        if (! $scanner->isEnabled()) {
            return;
        }

        $object = StoredObject::query()->where('bucket_id', $this->bucketId)->where('key', $this->objectKey)->first();

        if ($object === null || $object->bucket === null) {
            return;
        }

        $bucket = $object->bucket;
        $absolutePath = rtrim((string) config('fileserver.storage_root'), '/').'/'.$bucket->storage_path.'/'.$this->objectKey;

        if (! is_file($absolutePath)) {
            return;
        }

        $result = $scanner->scan($absolutePath);

        if ($result->clean) {
            return;
        }

        Log::warning('Quarantining infected object.', ['bucket' => $bucket->name, 'key' => $this->objectKey]);

        $storage->driverFor($bucket)->delete($bucket->storage_path, $this->objectKey);
        $object->delete();

        $auditLog->record(
            ActorType::System,
            null,
            'object.quarantined',
            $bucket->id,
            $this->objectKey,
            '127.0.0.1',
            'system',
            AuditStatus::Failure,
            ['signature' => $result->signature],
        );
    }
}
