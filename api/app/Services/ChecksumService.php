<?php

namespace App\Services;

class ChecksumService
{
    /**
     * Stream-hash a file on disk without loading it into memory.
     */
    public function sha256File(string $absolutePath): string
    {
        $hash = hash_init('sha256');
        $handle = fopen($absolutePath, 'rb');

        if ($handle === false) {
            throw new \RuntimeException("Unable to open file for checksum: {$absolutePath}");
        }

        try {
            hash_update_stream($hash, $handle);
        } finally {
            fclose($handle);
        }

        return hash_final($hash);
    }

    /**
     * S3-style ETag: for single-part uploads this is simply the MD5 of the
     * object content, quoted. Computed alongside the sha256 checksum since
     * both require a full pass over the file.
     */
    public function md5File(string $absolutePath): string
    {
        $hash = hash_init('md5');
        $handle = fopen($absolutePath, 'rb');

        if ($handle === false) {
            throw new \RuntimeException("Unable to open file for checksum: {$absolutePath}");
        }

        try {
            hash_update_stream($hash, $handle);
        } finally {
            fclose($handle);
        }

        return hash_final($hash);
    }
}
