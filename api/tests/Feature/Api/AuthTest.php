<?php

namespace Tests\Feature\Api;

use App\Enums\RoleSlug;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        // Sanctum only treats a request as "stateful" (session-cookie based)
        // when its Origin/Referer matches config('sanctum.stateful') — the
        // real SPA sends this automatically; tests must simulate it.
        $this->withHeader('Referer', 'http://127.0.0.1:5173');
    }

    public function test_login_me_logout_cycle(): void
    {
        $user = User::factory()->role(RoleSlug::SuperAdmin)->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'correct-password',
        ]);
        $login->assertStatus(200)->assertJsonPath('data.user.email', 'admin@example.com');

        $me = $this->getJson('/api/v1/auth/me');
        $me->assertStatus(200)->assertJsonPath('data.user.id', $user->id);

        $this->assertNotNull($user->fresh()->last_login_at);

        $logout = $this->postJson('/api/v1/auth/logout');
        $logout->assertStatus(200);

        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_login_rejects_wrong_password(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => Hash::make('right')]);

        $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    public function test_login_rejects_disabled_account(): void
    {
        User::factory()->disabled()->create(['email' => 'b@example.com', 'password' => Hash::make('right')]);

        $this->postJson('/api/v1/auth/login', ['email' => 'b@example.com', 'password' => 'right'])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'ACCOUNT_DISABLED');
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }
}
