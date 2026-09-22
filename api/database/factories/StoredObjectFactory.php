<?php

namespace Database\Factories;

use App\Models\Bucket;
use App\Models\StoredObject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoredObject>
 */
class StoredObjectFactory extends Factory
{
    protected $model = StoredObject::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $bytes = fake()->numberBetween(128, 5_000_000);

        return [
            'bucket_id' => Bucket::factory(),
            'key' => ltrim(fake()->unique()->filePath(), '/'),
            'size_bytes' => $bytes,
            'mime_type' => fake()->mimeType(),
            'checksum_sha256' => hash('sha256', fake()->uuid()),
            'etag' => fake()->md5(),
            'metadata' => [],
        ];
    }
}
