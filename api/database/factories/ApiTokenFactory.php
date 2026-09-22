<?php

namespace Database\Factories;

use App\Enums\PermissionKey;
use App\Enums\TokenStatus;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiToken>
 */
class ApiTokenFactory extends Factory
{
    protected $model = ApiToken::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $raw = ApiToken::generateRawToken();

        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true).' token',
            'token_hash' => ApiToken::hashToken($raw),
            'token_prefix' => ApiToken::prefixFor($raw),
            'abilities' => PermissionKey::tokenAssignableValues(),
            'status' => TokenStatus::Active,
            'expires_at' => null,
            'allowed_ips' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TokenStatus::Revoked,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }
}
