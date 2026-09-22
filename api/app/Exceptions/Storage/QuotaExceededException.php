<?php

namespace App\Exceptions\Storage;

class QuotaExceededException extends \RuntimeException
{
    public static function forBucket(string $bucketName, int $quotaBytes): self
    {
        return new self("Bucket [{$bucketName}] quota of {$quotaBytes} bytes would be exceeded by this upload.");
    }
}
