<?php

namespace Database\Factories;

use App\Enums\RoleSlug;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role_id' => Role::where('slug', RoleSlug::Developer->value)->value('id'),
            'status' => UserStatus::Active,
            'must_reset_password' => false,
        ];
    }

    public function role(RoleSlug $slug): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::where('slug', $slug->value)->value('id'),
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Disabled,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
