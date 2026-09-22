<?php

namespace App\Services\Storage;

/**
 * Immutable value object describing a single stored file, as returned by a
 * StorageDriverInterface::put()/list() call. Deliberately storage-driver
 * agnostic so it can represent a local file, an S3 object, or a MinIO object
 * without the caller knowing which.
 */
final readonly class FileObject
{
    public function __construct(
        public string $bucket,
        public string $key,
        public int $sizeBytes,
        public ?string $mimeType,
        public string $checksumSha256,
        public string $storageRelativePath,
        public \DateTimeImmutable $lastModified,
        public bool $isDirectory = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'bucket' => $this->bucket,
            'key' => $this->key,
            'size_bytes' => $this->sizeBytes,
            'mime_type' => $this->mimeType,
            'checksum_sha256' => $this->checksumSha256,
            'is_directory' => $this->isDirectory,
            'last_modified' => $this->lastModified->format(DATE_ATOM),
        ];
    }
}
