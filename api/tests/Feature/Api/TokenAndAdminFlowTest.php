<?php

namespace Tests\Feature\Api;

use App\Enums\PermissionKey;
use App\Enums\RoleSlug;
use App\Models\ApiToken;
use App\Models\Bucket;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TokenAndAdminFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $developer;

    private string $storageRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storageRoot = sys_get_temp_dir().'/fileserver_test_'.uniqid();
        config(['fileserver.storage_root' => $this->storageRoot]);

        $this->seed(RolePermissionSeeder::class);

        $this->superAdmin = User::factory()->role(RoleSlug::SuperAdmin)->create();
        $this->developer = User::factory()->role(RoleSlug::Developer)->create();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storageRoot);

        parent::tearDown();
    }

    public function test_admin_can_issue_a_token_for_another_user_with_bucket_scopes(): void
    {
        $bucket = Bucket::factory()->create();

        $response = $this->actingAs($this->superAdmin)->postJson('/api/v1/tokens', [
            'name' => 'CI deploy token',
            'user_id' => $this->developer->id,
            'abilities' => [PermissionKey::ObjectRead->value, PermissionKey::ObjectWrite->value],
            'bucket_scopes' => [[
                'bucket' => $bucket->name,
                'path_prefix' => 'builds/',
                'permissions' => [PermissionKey::ObjectRead->value, PermissionKey::ObjectWrite->value],
            ]],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.token.owner', $this->developer->name)
            ->assertJsonPath('data.token.bucket_scopes.0.bucket', $bucket->name);

        $this->assertNotEmpty($response->json('data.raw_token'));
        $this->assertStringStartsWith('fsk_', $response->json('data.raw_token'));

        // Raw token/hash never appear verbatim in the JSON body.
        $this->assertStringNotContainsString(
            ApiToken::hashToken($response->json('data.raw_token')),
            $response->getContent()
        );
    }

    public function test_developer_cannot_issue_a_token_for_another_user(): void
    {
        $other = User::factory()->role(RoleSlug::Developer)->create();

        $this->actingAs($this->developer)->postJson('/api/v1/tokens', [
            'name' => 'sneaky',
            'user_id' => $other->id,
            'abilities' => [PermissionKey::ObjectRead->value],
        ])->assertStatus(403);
    }

    public function test_owner_can_rotate_and_revoke_their_own_token_but_others_cannot(): void
    {
        $raw = ApiToken::generateRawToken();
        $token = ApiToken::factory()->create([
            'user_id' => $this->developer->id,
            'token_hash' => ApiToken::hashToken($raw),
            'token_prefix' => ApiToken::prefixFor($raw),
        ]);

        $stranger = User::factory()->role(RoleSlug::Developer)->create();
        $this->actingAs($stranger)->postJson("/api/v1/tokens/{$token->id}/rotate")->assertStatus(403);

        $rotate = $this->actingAs($this->developer)->postJson("/api/v1/tokens/{$token->id}/rotate");
        $rotate->assertStatus(200);
        $this->assertNotEquals($raw, $rotate->json('data.raw_token'));

        $this->actingAs($this->developer)->postJson("/api/v1/tokens/{$token->id}/revoke")
            ->assertStatus(200);
        $this->assertSame('revoked', $token->fresh()->status->value);
    }

    public function test_token_listing_is_scoped_to_owner_unless_admin(): void
    {
        ApiToken::factory()->count(2)->create(['user_id' => $this->developer->id]);
        ApiToken::factory()->count(3)->create(['user_id' => $this->superAdmin->id]);

        $ownList = $this->actingAs($this->developer)->getJson('/api/v1/tokens');
        $ownList->assertStatus(200);
        $this->assertCount(2, $ownList->json('data'));

        $adminList = $this->actingAs($this->superAdmin)->getJson('/api/v1/tokens');
        $adminList->assertStatus(200);
        $this->assertCount(5, $adminList->json('data'));
    }

    public function test_developer_is_blocked_from_admin_routes(): void
    {
        $this->actingAs($this->developer)->getJson('/api/v1/admin/users')->assertStatus(403);
        $this->actingAs($this->developer)->getJson('/api/v1/admin/dashboard/metrics')->assertStatus(403);
        $this->actingAs($this->developer)->getJson('/api/v1/audit-logs')->assertStatus(403);
    }

    public function test_admin_user_management_lifecycle(): void
    {
        $role = Role::where('slug', RoleSlug::ReadOnlyAuditor->value)->first();

        $create = $this->actingAs($this->superAdmin)->postJson('/api/v1/admin/users', [
            'name' => 'New Auditor',
            'email' => 'auditor@example.com',
            'role_id' => $role->id,
        ]);
        $create->assertStatus(201)->assertJsonPath('data.user.email', 'auditor@example.com');
        $this->assertNotEmpty($create->json('data.generated_password'));

        $userId = $create->json('data.user.id');

        $this->actingAs($this->superAdmin)->patchJson("/api/v1/admin/users/{$userId}", ['status' => 'disabled'])
            ->assertStatus(200)->assertJsonPath('data.status', 'disabled');

        $reset = $this->actingAs($this->superAdmin)->postJson("/api/v1/admin/users/{$userId}/force-password-reset");
        $reset->assertStatus(200);
        $this->assertNotEmpty($reset->json('data.generated_password'));

        $this->actingAs($this->superAdmin)->deleteJson("/api/v1/admin/users/{$userId}")->assertStatus(200);
        $this->assertSoftDeleted('users', ['id' => $userId]);
    }

    public function test_dashboard_metrics_and_audit_log_export_are_accessible_to_admin(): void
    {
        $this->actingAs($this->superAdmin)->postJson('/api/v1/buckets', ['name' => 'metrics-bucket'])->assertStatus(201);

        $metrics = $this->actingAs($this->superAdmin)->getJson('/api/v1/admin/dashboard/metrics');
        $metrics->assertStatus(200)->assertJsonStructure([
            'data' => ['buckets', 'storage', 'users', 'tokens', 'activity', 'recent_uploads'],
        ]);

        $logs = $this->actingAs($this->superAdmin)->getJson('/api/v1/audit-logs');
        $logs->assertStatus(200);
        $this->assertGreaterThan(0, count($logs->json('data')));

        $export = $this->actingAs($this->superAdmin)->get('/api/v1/audit-logs/export');
        $export->assertStatus(200);
        $this->assertStringContainsString('text/csv', $export->headers->get('Content-Type'));
    }
}
