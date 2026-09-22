<?php

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\Bucket;
use App\Models\User;
use App\Services\Authorization\AccessControlService;
use App\Support\CurrentActor;

class BucketPolicy
{
    public function __construct(private readonly AccessControlService $accessControl)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->accessControl->authorize($user, CurrentActor::token(), PermissionKey::BucketList->value);
    }

    public function view(User $user, Bucket $bucket): bool
    {
        return $this->accessControl->authorize($user, CurrentActor::token(), PermissionKey::BucketRead->value, $bucket);
    }

    public function create(User $user): bool
    {
        return $this->accessControl->authorize($user, CurrentActor::token(), PermissionKey::BucketWrite->value);
    }

    public function update(User $user, Bucket $bucket): bool
    {
        return $this->accessControl->authorize($user, CurrentActor::token(), PermissionKey::BucketWrite->value, $bucket);
    }

    public function delete(User $user, Bucket $bucket): bool
    {
        return $this->accessControl->authorize($user, CurrentActor::token(), PermissionKey::BucketDelete->value, $bucket);
    }
}
