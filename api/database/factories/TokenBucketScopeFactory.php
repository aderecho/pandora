<?php

namespace Database\Factories;

use App\Enums\PermissionKey;
use App\Models\ApiToken;
use App\Models\Bucket;
use App\Models\TokenBucketScope;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TokenBucketScope>
 */
class TokenBucketScopeFactory extends Factory
{
    protected $model = TokenBucketScope::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'api_token_id' => ApiToken::factory(),
            'bucket_id' => Bucket::factory(),
            'path_prefix' => null,
            'permissions' => PermissionKey::tokenAssignableValues(),
        ];
    }
}
