<?php

namespace Database\Factories;

use App\Enums\PermissionKey;
use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $case = fake()->unique()->randomElement(PermissionKey::cases());

        return [
            'key' => $case->value,
            'description' => $case->description(),
        ];
    }
}
