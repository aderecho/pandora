<?php

namespace App\Services;

use App\Enums\BucketStatus;
use App\Models\Bucket;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BucketService
{
    public function __construct(private readonly QuotaService $quota)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $creator): Bucket
    {
        $name = $data['name'];

        $bucket = Bucket::query()->create([
            'name' => $name,
            'slug' => Str::slug($name),
            'storage_path' => $data['storage_path'] ?? $name,
            'driver' => 'local',
            'quota_bytes' => $data['quota_bytes'] ?? config('fileserver.default_quota_bytes'),
            'max_file_size_bytes' => $data['max_file_size_bytes'] ?? config('fileserver.default_max_file_size_bytes'),
            'allowed_extensions' => $data['allowed_extensions'] ?? null,
            'allowed_mime_types' => $data['allowed_mime_types'] ?? null,
            'versioning_enabled' => $data['versioning_enabled'] ?? false,
            'public_access' => $data['public_access'] ?? false,
            'status' => BucketStatus::Active,
            'created_by' => $creator->id,
        ]);

        File::ensureDirectoryExists($this->absoluteRoot($bucket), 0755);

        return $bucket;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Bucket $bucket, array $data): Bucket
    {
        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $bucket->update($data);

        return $bucket->fresh();
    }

    public function delete(Bucket $bucket): void
    {
        $bucket->delete();
    }

    /**
     * @return array{file_count: int, total_bytes: int, quota_bytes: int|null}
     */
    public function usage(Bucket $bucket): array
    {
        return [
            'file_count' => $bucket->objects()->count(),
            'total_bytes' => $this->quota->currentUsageBytes($bucket),
            'quota_bytes' => $bucket->quota_bytes,
        ];
    }

    private function absoluteRoot(Bucket $bucket): string
    {
        return rtrim(config('fileserver.storage_root'), '/').'/'.$bucket->storage_path;
    }
}
