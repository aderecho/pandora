<?php

namespace Database\Factories;

use App\Models\ObjectVersion;
use App\Models\StoredObject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ObjectVersion>
 */
class ObjectVersionFactory extends Factory
{
    protected $model = ObjectVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stored_object_id' => StoredObject::factory(),
            'version_number' => 1,
            'size_bytes' => fake()->numberBetween(128, 5_000_000),
            'checksum_sha256' => hash('sha256', fake()->uuid()),
            'storage_relative_path' => fake()->uuid().'.bin',
            'is_current' => true,
        ];
    }
}
