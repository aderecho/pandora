<?php

namespace App\Jobs;

use App\Models\Bucket;
use App\Services\QuotaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Refreshes a cached usage figure for dashboard display. Quota *enforcement*
 * (QuotaService::assertFits) always runs a live SUM query and never trusts
 * this cache — it exists purely so the admin dashboard doesn't re-scan
 * every object on every page load for buckets with large object counts.
 */
class RecalculateBucketUsage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const string CACHE_PREFIX = 'bucket-usage-bytes:';

    public function __construct(public readonly int $bucketId)
    {
    }

    public function handle(QuotaService $quota): void
    {
        $bucket = Bucket::query()->find($this->bucketId);

        if ($bucket === null) {
            return;
        }

        Cache::put(self::CACHE_PREFIX.$bucket->id, $quota->currentUsageBytes($bucket), now()->addHour());
    }

    public static function dispatchForAllBuckets(): void
    {
        Bucket::query()->pluck('id')->each(fn (int $id) => self::dispatch($id));
    }
}
