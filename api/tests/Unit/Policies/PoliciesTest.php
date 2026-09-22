<?php

namespace Tests\Unit\Policies;

use App\Enums\RoleSlug;
use App\Models\ApiToken;
use App\Models\Bucket;
use App\Models\BucketUserPermission;
use App\Models\StoredObject;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PoliciesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_bucket_policy_allows_admins_and_blocks_non_admin_roles(): void
    {
        $bucket = Bucket::factory()->create();
        $admin = User::factory()->role(RoleSlug::SuperAdmin)->create();
        // Only Super Admin / Storage Admin hold bucket:write in the seeded
        // matrix, so no bucket_user_permissions grant can hand a Developer
        // bucket-level write — that authority is admin-tier only by design.
        $developer = User::factory()->role(RoleSlug::Developer)->create();

        $this->assertTrue(Gate::forUser($admin)->allows('update', $bucket));
        $this->assertFalse(Gate::forUser($developer)->allows('update', $bucket));
    }

    public function test_object_policy_requires_a_matching_bucket_grant_for_non_admin_roles(): void
    {
        $bucket = Bucket::factory()->create();
        $object = StoredObject::factory()->create(['bucket_id' => $bucket->id]);
        $admin = User::factory()->role(RoleSlug::SuperAdmin)->create();
        $developer = User::factory()->role(RoleSlug::Developer)->create();

        $this->assertTrue(Gate::forUser($admin)->allows('view', $object));
        // Developer's role grants object:read, but with no bucket_user_permissions
        // row yet, bucket-level scoping still denies it.
        $this->assertFalse(Gate::forUser($developer)->allows('view', $object));

        BucketUserPermission::factory()->create([
            'bucket_id' => $bucket->id,
            'user_id' => $developer->id,
            'can_read' => true,
        ]);

        $this->assertTrue(Gate::forUser($developer)->allows('view', $object));

        $auditor = User::factory()->role(RoleSlug::ReadOnlyAuditor)->create();
        $this->assertFalse(Gate::forUser($auditor)->allows('delete', $object)); // role has no object:delete permission at all
    }

    public function test_api_token_policy_allows_owner_and_blocks_strangers(): void
    {
        $owner = User::factory()->role(RoleSlug::Developer)->create();
        $stranger = User::factory()->role(RoleSlug::Developer)->create();
        $admin = User::factory()->role(RoleSlug::SuperAdmin)->create();
        $token = ApiToken::factory()->create(['user_id' => $owner->id]);

        $this->assertTrue(Gate::forUser($owner)->allows('rotate', $token));
        $this->assertFalse(Gate::forUser($stranger)->allows('rotate', $token));
        $this->assertTrue(Gate::forUser($admin)->allows('rotate', $token));
    }

    public function test_api_token_policy_blocks_disabled_users(): void
    {
        $owner = User::factory()->role(RoleSlug::Developer)->disabled()->create();
        $token = ApiToken::factory()->create(['user_id' => $owner->id]);

        $this->assertFalse(Gate::forUser($owner)->allows('rotate', $token));
    }
}
