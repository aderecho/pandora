<?php

namespace Database\Factories;

use App\Enums\BucketStatus;
use App\Models\Bucket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bucket>
 */
class BucketFactory extends Factory
{
    protected $model = Bucket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->slug(2);

        return [
            'name' => $name,
            'slug' => $name,
            'storage_path' => $name,
            'driver' => 'local',
            'quota_bytes' => config('fileserver.default_quota_bytes'),
            'max_file_size_bytes' => config('fileserver.default_max_file_size_bytes'),
            'allowed_extensions' => null,
            'allowed_mime_types' => null,
            'versioning_enabled' => false,
            'public_access' => false,
            'status' => BucketStatus::Active,
            'created_by' => User::factory(),
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BucketStatus::Archived,
        ]);
    }

    public function versioned(): static
    {
        return $this->state(fn (array $attributes) => [
            'versioning_enabled' => true,
        ]);
    }
}
