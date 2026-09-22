<?php

namespace App\Services;

use App\Exceptions\Storage\QuotaExceededException;
use App\Models\Bucket;

class QuotaService
{
    public function currentUsageBytes(Bucket $bucket): int
    {
        return (int) $bucket->objects()->sum('size_bytes');
    }

    public function wouldExceed(Bucket $bucket, int $additionalBytes): bool
    {
        if ($bucket->quota_bytes === null) {
            return false;
        }

        return $this->currentUsageBytes($bucket) + $additionalBytes > $bucket->quota_bytes;
    }

    public function assertFits(Bucket $bucket, int $additionalBytes): void
    {
        if ($this->wouldExceed($bucket, $additionalBytes)) {
            throw QuotaExceededException::forBucket($bucket->name, (int) $bucket->quota_bytes);
        }
    }
}
