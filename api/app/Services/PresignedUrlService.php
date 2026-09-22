<?php

namespace App\Services;

use App\Models\Bucket;
use Illuminate\Support\Facades\URL;

/**
 * Stateless HMAC-signed URLs — no database table to track or clean up.
 * The signature binds verb, bucket, key, expiry, and (for uploads) a max
 * byte size, so a signed download URL can't be replayed as an upload or
 * against a different key, and can't be tampered with to raise its size
 * limit.
 */
class PresignedUrlService
{
    public function __construct(private readonly string $appKey)
    {
    }

    /**
     * @return array{url: string, method: string, expires_at: string}
     */
    public function forDownload(Bucket $bucket, string $key, ?int $ttlSeconds = null): array
    {
        $expires = now()->addSeconds($ttlSeconds ?? (int) config('fileserver.presigned_url_ttl'))->timestamp;
        $signature = $this->sign('GET', $bucket->name, $key, $expires, null);

        $url = URL::to('/api/v1/public/objects/download').'?'.http_build_query([
            'bucket' => $bucket->name,
            'key' => $key,
            'expires' => $expires,
            'signature' => $signature,
        ]);

        return [
            'url' => $url,
            'method' => 'GET',
            'expires_at' => now()->createFromTimestamp($expires)->toAtomString(),
        ];
    }

    /**
     * @return array{url: string, method: string, expires_at: string, max_size_bytes: int|null}
     */
    public function forUpload(Bucket $bucket, string $key, ?int $maxSizeBytes = null, ?int $ttlSeconds = null): array
    {
        $expires = now()->addSeconds($ttlSeconds ?? (int) config('fileserver.presigned_url_ttl'))->timestamp;
        $signature = $this->sign('PUT', $bucket->name, $key, $expires, $maxSizeBytes);

        $query = [
            'bucket' => $bucket->name,
            'key' => $key,
            'expires' => $expires,
            'signature' => $signature,
        ];

        if ($maxSizeBytes !== null) {
            $query['max_size'] = $maxSizeBytes;
        }

        $url = URL::to('/api/v1/public/objects/upload').'?'.http_build_query($query);

        return [
            'url' => $url,
            'method' => 'PUT',
            'expires_at' => now()->createFromTimestamp($expires)->toAtomString(),
            'max_size_bytes' => $maxSizeBytes,
        ];
    }

    public function verify(string $verb, string $bucket, string $key, int $expires, ?int $maxSizeBytes, string $signature): bool
    {
        if ($expires < now()->timestamp) {
            return false;
        }

        $expected = $this->sign($verb, $bucket, $key, $expires, $maxSizeBytes);

        return hash_equals($expected, $signature);
    }

    private function sign(string $verb, string $bucket, string $key, int $expires, ?int $maxSizeBytes): string
    {
        $payload = implode('|', [$verb, $bucket, $key, $expires, $maxSizeBytes ?? '']);

        return hash_hmac('sha256', $payload, $this->appKey);
    }
}
