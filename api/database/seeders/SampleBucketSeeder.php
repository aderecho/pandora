<?php

namespace Database\Seeders;

use App\Enums\BucketStatus;
use App\Enums\RoleSlug;
use App\Models\Bucket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class SampleBucketSeeder extends Seeder
{
    public function run(): void
    {
        if (Bucket::query()->where('name', 'documents')->exists()) {
            $this->command?->warn('Sample bucket "documents" already exists; skipping.');

            return;
        }

        $owner = User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', RoleSlug::SuperAdmin->value))
            ->first();

        $bucket = Bucket::query()->create([
            'name' => 'documents',
            'slug' => 'documents',
            'storage_path' => 'documents',
            'driver' => 'local',
            'quota_bytes' => config('fileserver.default_quota_bytes'),
            'max_file_size_bytes' => config('fileserver.default_max_file_size_bytes'),
            'allowed_extensions' => null,
            'allowed_mime_types' => null,
            'versioning_enabled' => true,
            'public_access' => false,
            'status' => BucketStatus::Active,
            'created_by' => $owner?->id,
        ]);

        File::ensureDirectoryExists(rtrim(config('fileserver.storage_root'), '/').'/'.$bucket->storage_path);

        $this->command?->info('Sample bucket "documents" created.');
    }
}
