<?php

namespace App\Services\Authorization;

use App\Enums\PermissionKey;
use App\Enums\RoleSlug;
use App\Models\ApiToken;
use App\Models\Bucket;
use App\Models\User;

/**
 * Single source of truth for "can this actor do $permission, optionally
 * scoped to $bucket/$key". Used by both the ScopedPermission middleware
 * (S3-style bucket/object routes, reachable by session users and API
 * tokens alike) and by Policies (admin-only resources).
 *
 * Token requests: the token must carry the ability AND a token_bucket_scopes
 * row for the bucket whose permissions include it and whose path_prefix (if
 * any) matches the key. Tokens with no scope rows for a bucket have no
 * access to it, even if the ability is present — least privilege by default.
 *
 * Session requests: the user's role must grant the permission. Super Admin
 * and Storage Admin roles then have unrestricted bucket access; all other
 * roles additionally need a matching bucket_user_permissions grant.
 */
class AccessControlService
{
    private const array ADMIN_TIER_ROLES = [RoleSlug::SuperAdmin, RoleSlug::StorageAdmin];

    public function authorize(User $user, ?ApiToken $token, string $permission, ?Bucket $bucket = null, ?string $key = null): bool
    {
        if ($token !== null) {
            return $this->authorizeToken($token, $permission, $bucket, $key);
        }

        return $this->authorizeUser($user, $permission, $bucket, $key);
    }

    private function authorizeToken(ApiToken $token, string $permission, ?Bucket $bucket, ?string $key): bool
    {
        if (! $token->isActive() || ! $token->hasAbility($permission)) {
            return false;
        }

        if ($bucket === null) {
            return true;
        }

        $scopes = $token->relationLoaded('bucketScopes')
            ? $token->bucketScopes->where('bucket_id', $bucket->id)
            : $token->bucketScopes()->where('bucket_id', $bucket->id)->get();

        foreach ($scopes as $scope) {
            if ($scope->grants($permission) && $scope->allowsPath($key ?? '')) {
                return true;
            }
        }

        return false;
    }

    private function authorizeUser(User $user, string $permission, ?Bucket $bucket, ?string $key): bool
    {
        if (! $user->isActive() || ! $user->hasPermission($permission)) {
            return false;
        }

        if ($bucket === null) {
            return true;
        }

        if ($this->isAdminTier($user)) {
            return true;
        }

        $grant = $user->bucketPermissions()->where('bucket_id', $bucket->id)->get();

        foreach ($grant as $permissionRow) {
            if ($this->grantAllows($permissionRow, $permission) && $permissionRow->allowsPath($key ?? '')) {
                return true;
            }
        }

        return false;
    }

    private function isAdminTier(User $user): bool
    {
        $slug = $user->role?->slug;

        return $slug !== null && in_array(RoleSlug::from($slug), self::ADMIN_TIER_ROLES, true);
    }

    private function grantAllows(\App\Models\BucketUserPermission $grant, string $permission): bool
    {
        return match ($permission) {
            PermissionKey::BucketList->value, PermissionKey::ObjectList->value => $grant->can_list,
            PermissionKey::BucketRead->value, PermissionKey::ObjectRead->value => $grant->can_read,
            PermissionKey::BucketWrite->value, PermissionKey::ObjectWrite->value => $grant->can_write,
            PermissionKey::BucketDelete->value, PermissionKey::ObjectDelete->value => $grant->can_delete,
            default => false,
        };
    }
}
