<?php

namespace App\Services\Storage;

use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Contract every storage backend must satisfy. $bucket identifies the
 * bucket (its configured storage path, not necessarily the display name)
 * and $key is the object's path within that bucket. Implementations MUST
 * reject any $bucket/$key that would resolve outside the bucket's root
 * (see LocalStorageDriver for the reference containment check) so that a
 * future S3/MinIO driver can be swapped in without changing API or CLI
 * behavior.
 */
interface StorageDriverInterface
{
    /**
     * Store $file at $bucket/$key and return a description of the written
     * object. Implementations must stream the source rather than buffering
     * it fully in memory, so large uploads don't exhaust PHP's memory limit.
     *
     * @param  UploadedFile|resource|string  $file  An uploaded file instance, an open
     *                                               PHP stream resource, or an absolute
     *                                               path to a source file.
     */
    public function put(string $bucket, string $key, mixed $file): FileObject;

    /**
     * Return a streamed HTTP response for $bucket/$key without loading the
     * whole object into memory.
     */
    public function get(string $bucket, string $key): StreamedResponse;

    public function delete(string $bucket, string $key): bool;

    /**
     * List objects directly under $prefix within $bucket (S3-style: one
     * level of nesting per call, not recursive).
     *
     * @return array<int, FileObject>
     */
    public function list(string $bucket, string $prefix = ''): array;

    public function exists(string $bucket, string $key): bool;

    /**
     * Create an empty folder/prefix. For drivers with a genuinely flat
     * namespace (S3, MinIO) this is conventionally implemented as a
     * zero-byte object whose key ends in "/".
     */
    public function makeDirectory(string $bucket, string $prefix): bool;
}
