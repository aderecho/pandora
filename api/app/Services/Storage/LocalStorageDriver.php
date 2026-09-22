<?php

namespace App\Services\Storage;

use App\Exceptions\Storage\ObjectNotFoundException;
use App\Exceptions\Storage\PathTraversalException;
use App\Services\ChecksumService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Filesystem-backed implementation of StorageDriverInterface. $bucket is the
 * bucket's storage-path segment (see buckets.storage_path), not necessarily
 * its display name, and every path is resolved and containment-checked
 * against $storageRoot before any read/write/delete touches disk.
 */
class LocalStorageDriver implements StorageDriverInterface
{
    public function __construct(
        private readonly string $storageRoot,
        private readonly ChecksumService $checksums,
    ) {}

    public function put(string $bucket, string $key, mixed $file): FileObject
    {
        $absolute = $this->resolveForWrite($bucket, $key);

        File::ensureDirectoryExists(dirname($absolute), 0755);

        if ($file instanceof UploadedFile) {
            $file->move(dirname($absolute), basename($absolute));
        } elseif (is_resource($file)) {
            $destination = fopen($absolute, 'wb');
            if ($destination === false) {
                throw new \RuntimeException("Unable to open destination for writing: {$key}");
            }
            try {
                stream_copy_to_stream($file, $destination);
            } finally {
                fclose($destination);
            }
        } elseif (is_string($file)) {
            if (! is_file($file)) {
                throw new \InvalidArgumentException("Source file does not exist: {$file}");
            }
            if (! copy($file, $absolute)) {
                throw new \RuntimeException("Unable to copy source file to: {$key}");
            }
        } else {
            throw new \InvalidArgumentException('Unsupported $file argument; expected UploadedFile, resource, or file path string.');
        }

        $this->assertResolvesWithinRoot($absolute);

        return $this->describe($bucket, $key, $absolute);
    }

    public function get(string $bucket, string $key): StreamedResponse
    {
        $absolute = $this->resolveExisting($bucket, $key);

        if (! is_file($absolute)) {
            throw ObjectNotFoundException::forKey($bucket, $key);
        }

        $mimeType = File::mimeType($absolute) ?: 'application/octet-stream';
        $size = filesize($absolute);
        $checksum = $this->checksums->sha256File($absolute);

        $response = new StreamedResponse(function () use ($absolute) {
            $handle = fopen($absolute, 'rb');
            if ($handle === false) {
                return;
            }
            fpassthru($handle);
            fclose($handle);
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) $size,
            'Content-Disposition' => 'attachment; filename="'.basename($key).'"',
            'ETag' => '"'.$checksum.'"',
        ]);

        return $response;
    }

    public function delete(string $bucket, string $key): bool
    {
        $absolute = $this->resolveExisting($bucket, $key);

        if (! is_file($absolute)) {
            return false;
        }

        $deleted = unlink($absolute);

        if ($deleted) {
            $this->pruneEmptyDirectories(dirname($absolute), $bucket);
        }

        return $deleted;
    }

    public function list(string $bucket, string $prefix = ''): array
    {
        $this->assertSafeSegment($bucket, 'bucket');

        if ($prefix !== '') {
            $this->assertSafeSegment($prefix, 'prefix');
        }

        $directory = $this->bucketRoot($bucket).($prefix !== '' ? '/'.trim($prefix, '/') : '');

        if (! is_dir($directory)) {
            return [];
        }

        $this->assertResolvesWithinRoot($directory, requireExists: true);

        $entries = [];
        $prefixNormalized = $prefix !== '' ? rtrim($prefix, '/').'/' : '';

        foreach (scandir($directory) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $entryAbsolute = $directory.'/'.$name;
            $entryKey = $prefixNormalized.$name;

            if (is_dir($entryAbsolute)) {
                $entries[] = new FileObject(
                    bucket: $bucket,
                    key: $entryKey.'/',
                    sizeBytes: 0,
                    mimeType: null,
                    checksumSha256: '',
                    storageRelativePath: $bucket.'/'.$entryKey,
                    lastModified: (new \DateTimeImmutable())->setTimestamp((int) filemtime($entryAbsolute)),
                    isDirectory: true,
                );
            } else {
                $entries[] = $this->describe($bucket, $entryKey, $entryAbsolute);
            }
        }

        usort($entries, fn (FileObject $a, FileObject $b) => [! $a->isDirectory, $a->key] <=> [! $b->isDirectory, $b->key]);

        return $entries;
    }

    public function exists(string $bucket, string $key): bool
    {
        $absolute = $this->resolveExisting($bucket, $key);

        return is_file($absolute) || is_dir($absolute);
    }

    public function makeDirectory(string $bucket, string $prefix): bool
    {
        $this->assertSafeSegment($bucket, 'bucket');
        $this->assertSafeSegment($prefix, 'prefix');

        $absolute = $this->bucketRoot($bucket).'/'.trim($prefix, '/');

        File::ensureDirectoryExists($absolute, 0755);

        $this->assertResolvesWithinRoot($absolute, requireExists: true);

        return true;
    }

    private function describe(string $bucket, string $key, string $absolute): FileObject
    {
        return new FileObject(
            bucket: $bucket,
            key: $key,
            sizeBytes: filesize($absolute) ?: 0,
            mimeType: File::mimeType($absolute) ?: null,
            checksumSha256: $this->checksums->sha256File($absolute),
            storageRelativePath: $bucket.'/'.$key,
            lastModified: (new \DateTimeImmutable())->setTimestamp((int) filemtime($absolute)),
        );
    }

    private function bucketRoot(string $bucket): string
    {
        return rtrim($this->storageRoot, '/').'/'.$bucket;
    }

    /**
     * Resolve $bucket/$key to an absolute path for a WRITE. The file need
     * not exist yet, but every path segment is validated and the resolved
     * parent directory (once created) is verified to sit inside the
     * bucket's storage root.
     */
    private function resolveForWrite(string $bucket, string $key): string
    {
        $this->assertSafeSegment($bucket, 'bucket');
        $this->assertSafeSegment($key, 'key');

        return $this->bucketRoot($bucket).'/'.$key;
    }

    /**
     * Resolve $bucket/$key for a read/delete/exists check against a path
     * that may or may not currently exist on disk.
     */
    private function resolveExisting(string $bucket, string $key): string
    {
        $this->assertSafeSegment($bucket, 'bucket');
        $this->assertSafeSegment($key, 'key');

        $absolute = $this->bucketRoot($bucket).'/'.$key;

        if (is_file($absolute) || is_dir($absolute)) {
            $this->assertResolvesWithinRoot($absolute, requireExists: true);
        }

        return $absolute;
    }

    private function assertSafeSegment(string $segment, string $label): void
    {
        if ($segment === '' || str_contains($segment, "\0")) {
            throw PathTraversalException::forSegment($label);
        }

        if (str_starts_with($segment, '/') || str_starts_with($segment, '\\')) {
            throw PathTraversalException::forSegment($label);
        }

        $parts = explode('/', str_replace('\\', '/', $segment));

        foreach ($parts as $part) {
            if ($part === '..' || $part === '.') {
                throw PathTraversalException::forSegment($label);
            }
        }
    }

    /**
     * Final defense-in-depth check: resolve the real, symlink-free path and
     * confirm it still lives inside the configured storage root.
     */
    private function assertResolvesWithinRoot(string $absolute, bool $requireExists = false): void
    {
        $root = realpath($this->storageRoot);

        if ($root === false) {
            throw new \RuntimeException('Storage root does not exist or is not accessible.');
        }

        $check = is_dir($absolute) ? $absolute : dirname($absolute);
        $real = realpath($check);

        if ($real === false) {
            if ($requireExists) {
                throw PathTraversalException::forSegment('path');
            }

            return;
        }

        if (! str_starts_with($real.DIRECTORY_SEPARATOR, $root.DIRECTORY_SEPARATOR)) {
            throw PathTraversalException::forSegment('path');
        }
    }

    /**
     * Remove now-empty directories walking up from $from, stopping at the
     * bucket root (folders are never pruned past the bucket boundary).
     */
    private function pruneEmptyDirectories(string $from, string $bucket): void
    {
        $root = rtrim($this->bucketRoot($bucket), '/');
        $current = rtrim($from, '/');

        while (str_starts_with($current.'/', $root.'/') && $current !== $root) {
            $items = scandir($current);
            if ($items === false || count($items) > 2) {
                break;
            }

            if (! @rmdir($current)) {
                break;
            }

            $current = dirname($current);
        }
    }
}
