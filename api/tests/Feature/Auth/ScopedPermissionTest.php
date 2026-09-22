<?php

namespace Tests\Feature\Auth;

use App\Enums\PermissionKey;
use App\Enums\RoleSlug;
use App\Models\ApiToken;
use App\Models\Bucket;
use App\Models\BucketUserPermission;
use App\Models\Role;
use App\Models\TokenBucketScope;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ScopedPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $handler = fn (Bucket $bucket, string $key): JsonResponse => response()->json(['ok' => true]);

        Route::middleware(['api', 'auth.token', 'scope:object:write'])
            ->get('/_test/token/{bucket}/{key}', $handler)
            ->where('key', '.*');

        Route::middleware(['api', 'auth', 'scope:object:write'])
            ->get('/_test/session/{bucket}/{key}', $handler)
            ->where('key', '.*');
    }

    public function test_request_without_token_is_unauthenticated(): void
    {
        $bucket = Bucket::factory()->create();

        $this->getJson("/_test/token/{$bucket->name}/reports/a.txt")
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_request_with_invalid_token_is_unauthenticated(): void
    {
        $bucket = Bucket::factory()->create();

        $this->withHeader('Authorization', 'Bearer not-a-real-token')
            ->getJson("/_test/token/{$bucket->name}/reports/a.txt")
            ->assertStatus(401);
    }

    public function test_token_without_ability_is_forbidden(): void
    {
        $bucket = Bucket::factory()->create();
        [$raw, $token] = $this->makeToken(abilities: [PermissionKey::ObjectRead->value]);

        TokenBucketScope::factory()->create([
            'api_token_id' => $token->id,
            'bucket_id' => $bucket->id,
            'permissions' => [PermissionKey::ObjectRead->value],
        ]);

        $this->withHeader('Authorization', "Bearer {$raw}")
            ->getJson("/_test/token/{$bucket->name}/reports/a.txt")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_token_without_bucket_scope_is_forbidden(): void
    {
        $bucket = Bucket::factory()->create();
        [$raw] = $this->makeToken(abilities: [PermissionKey::ObjectWrite->value]);
        // No TokenBucketScope row created for this bucket at all.

        $this->withHeader('Authorization', "Bearer {$raw}")
            ->getJson("/_test/token/{$bucket->name}/reports/a.txt")
            ->assertStatus(403);
    }

    public function test_token_with_mismatched_path_prefix_is_forbidden(): void
    {
        $bucket = Bucket::factory()->create();
        [$raw, $token] = $this->makeToken(abilities: [PermissionKey::ObjectWrite->value]);

        TokenBucketScope::factory()->create([
            'api_token_id' => $token->id,
            'bucket_id' => $bucket->id,
            'path_prefix' => 'invoices/',
            'permissions' => [PermissionKey::ObjectWrite->value],
        ]);

        $this->withHeader('Authorization', "Bearer {$raw}")
            ->getJson("/_test/token/{$bucket->name}/reports/a.txt")
            ->assertStatus(403);
    }

    public function test_token_with_matching_ability_and_scope_is_allowed(): void
    {
        $bucket = Bucket::factory()->create();
        [$raw, $token] = $this->makeToken(abilities: [PermissionKey::ObjectWrite->value]);

        TokenBucketScope::factory()->create([
            'api_token_id' => $token->id,
            'bucket_id' => $bucket->id,
            'path_prefix' => 'reports/',
            'permissions' => [PermissionKey::ObjectWrite->value],
        ]);

        $this->withHeader('Authorization', "Bearer {$raw}")
            ->getJson("/_test/token/{$bucket->name}/reports/a.txt")
            ->assertStatus(200)
            ->assertJson(['ok' => true]);

        $this->assertNotNull($token->fresh()->last_used_at);
    }

    public function test_developer_without_bucket_grant_is_forbidden_even_with_role_permission(): void
    {
        $bucket = Bucket::factory()->create();
        $user = User::factory()->role(RoleSlug::Developer)->create();

        $this->actingAs($user)
            ->getJson("/_test/session/{$bucket->name}/reports/a.txt")
            ->assertStatus(403);
    }

    public function test_developer_with_bucket_grant_is_allowed(): void
    {
        $bucket = Bucket::factory()->create();
        $user = User::factory()->role(RoleSlug::Developer)->create();

        BucketUserPermission::factory()->create([
            'bucket_id' => $bucket->id,
            'user_id' => $user->id,
            'can_write' => true,
        ]);

        $this->actingAs($user)
            ->getJson("/_test/session/{$bucket->name}/reports/a.txt")
            ->assertStatus(200);
    }

    public function test_storage_admin_has_unrestricted_bucket_access(): void
    {
        $bucket = Bucket::factory()->create();
        $user = User::factory()->role(RoleSlug::StorageAdmin)->create();

        $this->actingAs($user)
            ->getJson("/_test/session/{$bucket->name}/reports/a.txt")
            ->assertStatus(200);
    }

    /**
     * @return array{0: string, 1: ApiToken}
     */
    private function makeToken(array $abilities): array
    {
        $role = Role::where('slug', RoleSlug::Developer->value)->first();
        $owner = User::factory()->create(['role_id' => $role->id]);

        $raw = ApiToken::generateRawToken();
        $token = ApiToken::factory()->create([
            'user_id' => $owner->id,
            'token_hash' => ApiToken::hashToken($raw),
            'token_prefix' => ApiToken::prefixFor($raw),
            'abilities' => $abilities,
        ]);

        return [$raw, $token];
    }
}
