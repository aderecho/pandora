<?php

namespace App\Services;

use App\Exceptions\Storage\FileRejectedException;
use App\Exceptions\Storage\ObjectNotFoundException;
use App\Jobs\ScanObjectForViruses;
use App\Models\ApiToken;
use App\Models\Bucket;
use App\Models\StoredObject;
use App\Models\User;
use App\Services\Storage\FileObject;
use App\Services\Storage\StorageManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ObjectService
{
    public function __construct(
        private readonly StorageManager $storage,
        private readonly QuotaService $quota,
    ) {}

    public function upload(Bucket $bucket, string $key, UploadedFile $file, ?User $user, ?ApiToken $token): StoredObject
    {
        $this->assertPolicyAllows($bucket, $key, $file->getSize());

        $driver = $this->storage->driverFor($bucket);
        [$existing, $nextVersionNumber] = $this->resolveNextVersion($bucket, $key);

        $fileObject = $driver->put($bucket->storage_path, $key, $file);

        return $this->finishUpload($bucket, $key, $fileObject, $existing, $nextVersionNumber, $user, $token);
    }

    /**
     * Used by the presigned-upload endpoint: the request body arrives as a
     * raw stream (no multipart metadata), so size/extension/MIME can only
     * be enforced once the bytes are on disk — violations delete the file
     * and reject, rather than pre-validating an UploadedFile.
     *
     * @param  resource  $stream
     */
    public function uploadStream(Bucket $bucket, string $key, mixed $stream, ?int $presignedMaxSizeBytes): StoredObject
    {
        $driver = $this->storage->driverFor($bucket);
        [$existing, $nextVersionNumber] = $this->resolveNextVersion($bucket, $key);

        $fileObject = $driver->put($bucket->storage_path, $key, $stream);

        $effectiveMax = $bucket->max_file_size_bytes ?? config('fileserver.default_max_file_size_bytes');
        if ($presignedMaxSizeBytes !== null) {
            $effectiveMax = $effectiveMax !== null ? min($effectiveMax, $presignedMaxSizeBytes) : $presignedMaxSizeBytes;
        }

        try {
            if ($effectiveMax !== null && $fileObject->sizeBytes > $effectiveMax) {
                throw FileRejectedException::because("File exceeds the allowed maximum size of {$effectiveMax} bytes.");
            }

            $this->assertPolicyAllows($bucket, $key, $fileObject->sizeBytes, skipSizeCheck: true);
        } catch (FileRejectedException $e) {
            $driver->delete($bucket->storage_path, $key);
            throw $e;
        }

        return $this->finishUpload($bucket, $key, $fileObject, $existing, $nextVersionNumber, null, null);
    }

    public function download(Bucket $bucket, string $key): StreamedResponse
    {
        $this->findLive($bucket, $key);

        return $this->storage->driverFor($bucket)->get($bucket->storage_path, $key);
    }

    public function delete(Bucket $bucket, string $key): StoredObject
    {
        $object = $this->findLive($bucket, $key);

        $this->storage->driverFor($bucket)->delete($bucket->storage_path, $key);
        $object->delete();

        return $object;
    }

    public function metadata(Bucket $bucket, string $key): StoredObject
    {
        return $this->findLive($bucket, $key, ['versions', 'uploader', 'uploadToken']);
    }

    public function makeDirectory(Bucket $bucket, string $prefix): StoredObject
    {
        $prefix = rtrim($prefix, '/').'/';

        $existing = StoredObject::query()->where('bucket_id', $bucket->id)->where('key', $prefix)->first();

        if ($existing) {
            return $existing;
        }

        $this->storage->driverFor($bucket)->makeDirectory($bucket->storage_path, $prefix);

        return StoredObject::query()->create([
            'bucket_id' => $bucket->id,
            'key' => $prefix,
            'size_bytes' => 0,
            'mime_type' => null,
            'checksum_sha256' => str_repeat('0', 64),
            'etag' => null,
            'metadata' => [],
        ]);
    }

    /**
     * S3-style single-level listing: objects and "folders" (common prefixes)
     * directly under $prefix, backed by the stored_objects index rather
     * than a filesystem scan — which also lets it double as the richer
     * search the admin file browser needs (name/mime/uploader/date).
     *
     * @param  array{name?: string, mime_type?: string, uploaded_by?: int, from?: string, to?: string}  $filters
     */
    public function list(Bucket $bucket, string $prefix, array $filters, int $page, int $perPage): LengthAwarePaginator
    {
        $query = StoredObject::query()->where('bucket_id', $bucket->id);

        if ($prefix !== '') {
            $query->where('key', 'like', $this->escapeLike($prefix).'%');
        }

        if (! empty($filters['name'])) {
            $query->where('key', 'like', '%'.$this->escapeLike($filters['name']).'%');
        }

        if (! empty($filters['mime_type'])) {
            $query->where('mime_type', $filters['mime_type']);
        }

        if (! empty($filters['uploaded_by'])) {
            $query->where('uploaded_by', $filters['uploaded_by']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        $all = $query->orderBy('key')->get();

        $prefixLength = strlen($prefix);
        $folders = [];
        $files = [];

        foreach ($all as $object) {
            $rest = substr($object->key, $prefixLength);

            if ($rest === '') {
                continue;
            }

            $slashPos = strpos($rest, '/');

            if ($slashPos !== false) {
                $folderKey = $prefix.substr($rest, 0, $slashPos + 1);
                $folders[$folderKey] = $folderKey;
            } else {
                $files[$object->key] = $object;
            }
        }

        $entries = [];

        foreach ($folders as $folderKey) {
            $entries[] = ['type' => 'folder', 'key' => $folderKey];
        }

        foreach ($files as $object) {
            $entries[] = ['type' => 'file', 'object' => $object];
        }

        $total = count($entries);
        $slice = array_slice($entries, ($page - 1) * $perPage, $perPage);

        return new LengthAwarePaginator($slice, $total, $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]);
    }

    /**
     * @return array{0: ?StoredObject, 1: int}
     */
    private function resolveNextVersion(Bucket $bucket, string $key): array
    {
        $existing = StoredObject::query()->where('bucket_id', $bucket->id)->where('key', $key)->first();
        $nextVersionNumber = $existing ? ((int) $existing->versions()->max('version_number')) + 1 : 1;

        return [$existing, $nextVersionNumber];
    }

    private function assertPolicyAllows(Bucket $bucket, string $key, int $sizeBytes, bool $skipSizeCheck = false): void
    {
        if (! $skipSizeCheck) {
            $maxSize = $bucket->max_file_size_bytes ?? config('fileserver.default_max_file_size_bytes');
            if ($maxSize !== null && $sizeBytes > $maxSize) {
                throw FileRejectedException::because("File exceeds the bucket's maximum file size of {$maxSize} bytes.");
            }
        }

        if (! $bucket->isExtensionAllowed(pathinfo($key, PATHINFO_EXTENSION))) {
            throw FileRejectedException::because('File extension is not allowed for this bucket.');
        }

        $this->quota->assertFits($bucket, $sizeBytes);
    }

    private function finishUpload(
        Bucket $bucket,
        string $key,
        FileObject $fileObject,
        ?StoredObject $existing,
        int $nextVersionNumber,
        ?User $user,
        ?ApiToken $token,
    ): StoredObject {
        $driver = $this->storage->driverFor($bucket);

        if (! $bucket->isMimeTypeAllowed((string) $fileObject->mimeType)) {
            $driver->delete($bucket->storage_path, $key);
            throw FileRejectedException::because('File MIME type is not allowed for this bucket.');
        }

        if ($bucket->versioning_enabled) {
            $driver->put($bucket->storage_path, $this->versionedKey($key, $nextVersionNumber), $this->absolutePathFor($bucket, $key));
        }

        $storedObject = DB::transaction(function () use ($bucket, $key, $fileObject, $existing, $nextVersionNumber, $user, $token) {
            if ($existing) {
                $existing->update([
                    'size_bytes' => $fileObject->sizeBytes,
                    'mime_type' => $fileObject->mimeType,
                    'checksum_sha256' => $fileObject->checksumSha256,
                    'etag' => $fileObject->checksumSha256,
                    'uploaded_by' => $user?->id,
                    'uploaded_via_token_id' => $token?->id,
                ]);
                $storedObject = $existing;
            } else {
                $storedObject = StoredObject::query()->create([
                    'bucket_id' => $bucket->id,
                    'key' => $key,
                    'size_bytes' => $fileObject->sizeBytes,
                    'mime_type' => $fileObject->mimeType,
                    'checksum_sha256' => $fileObject->checksumSha256,
                    'etag' => $fileObject->checksumSha256,
                    'uploaded_by' => $user?->id,
                    'uploaded_via_token_id' => $token?->id,
                    'metadata' => [],
                ]);
            }

            if ($bucket->versioning_enabled) {
                $storedObject->versions()->where('is_current', true)->update(['is_current' => false]);

                $version = $storedObject->versions()->create([
                    'version_number' => $nextVersionNumber,
                    'size_bytes' => $fileObject->sizeBytes,
                    'checksum_sha256' => $fileObject->checksumSha256,
                    'storage_relative_path' => $this->versionedKey($key, $nextVersionNumber),
                    'uploaded_by' => $user?->id,
                    'is_current' => true,
                ]);
            } else {
                $version = $storedObject->versions()->where('version_number', 1)->first();

                if ($version) {
                    $version->update([
                        'size_bytes' => $fileObject->sizeBytes,
                        'checksum_sha256' => $fileObject->checksumSha256,
                        'uploaded_by' => $user?->id,
                    ]);
                } else {
                    $version = $storedObject->versions()->create([
                        'version_number' => 1,
                        'size_bytes' => $fileObject->sizeBytes,
                        'checksum_sha256' => $fileObject->checksumSha256,
                        'storage_relative_path' => $key,
                        'uploaded_by' => $user?->id,
                        'is_current' => true,
                    ]);
                }
            }

            $storedObject->update(['current_version_id' => $version->id]);

            return $storedObject->fresh(['currentVersion']);
        });

        ScanObjectForViruses::dispatch($bucket->id, $key);

        return $storedObject;
    }

    private function findLive(Bucket $bucket, string $key, array $with = []): StoredObject
    {
        $object = StoredObject::query()
            ->with($with)
            ->where('bucket_id', $bucket->id)
            ->where('key', $key)
            ->first();

        if ($object === null) {
            throw ObjectNotFoundException::forKey($bucket->name, $key);
        }

        return $object;
    }

    private function versionedKey(string $key, int $versionNumber): string
    {
        return '.versions/'.sha1($key).'/v'.$versionNumber.'__'.basename($key);
    }

    private function absolutePathFor(Bucket $bucket, string $relativeKey): string
    {
        return rtrim((string) config('fileserver.storage_root'), '/').'/'.$bucket->storage_path.'/'.$relativeKey;
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
