<?php

namespace Database\Factories;

use App\Enums\ActorType;
use App\Enums\AuditStatus;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_type' => ActorType::User,
            'actor_id' => null,
            'action' => fake()->randomElement([
                'object.upload', 'object.download', 'object.delete',
                'bucket.create', 'bucket.update', 'token.rotate', 'auth.login',
            ]),
            'bucket_id' => null,
            'object_key' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'status' => AuditStatus::Success,
            'metadata' => [],
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AuditStatus::Failure,
        ]);
    }
}
