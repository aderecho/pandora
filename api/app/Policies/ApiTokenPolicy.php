<?php

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\ApiToken;
use App\Models\User;

/**
 * Every authenticated, active user may create and manage their own tokens.
 * Managing another user's token additionally requires the tokens:manage
 * role permission (Storage Admin / Super Admin).
 */
class ApiTokenPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, ApiToken $token): bool
    {
        return $this->ownsOrManages($user, $token);
    }

    public function create(User $user): bool
    {
        return $user->isActive();
    }

    public function update(User $user, ApiToken $token): bool
    {
        return $this->ownsOrManages($user, $token);
    }

    public function rotate(User $user, ApiToken $token): bool
    {
        return $this->ownsOrManages($user, $token);
    }

    public function revoke(User $user, ApiToken $token): bool
    {
        return $this->ownsOrManages($user, $token);
    }

    private function ownsOrManages(User $user, ApiToken $token): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        return $token->user_id === $user->id || $user->hasPermission(PermissionKey::TokensManage->value);
    }
}
