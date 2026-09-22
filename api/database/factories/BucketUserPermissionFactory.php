<?php

namespace Database\Factories;

use App\Models\Bucket;
use App\Models\BucketUserPermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BucketUserPermission>
 */
class BucketUserPermissionFactory extends Factory
{
    protected $model = BucketUserPermission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bucket_id' => Bucket::factory(),
            'user_id' => User::factory(),
            'can_list' => true,
            'can_read' => true,
            'can_write' => false,
            'can_delete' => false,
            'path_prefix' => null,
        ];
    }
}
