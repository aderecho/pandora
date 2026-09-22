<?php

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\Bucket;
use App\Models\StoredObject;
use App\Models\User;
use App\Services\Authorization\AccessControlService;
use App\Support\CurrentActor;

/**
 * Governs StoredObject access. "upload" is checked against a Bucket (the
 * object may not exist yet), everything else against an existing
 * StoredObject instance.
 */
class ObjectPolicy
{
    public function __construct(private readonly AccessControlService $accessControl)
    {
    }

    public function viewAny(User $user, Bucket $bucket): bool
    {
        return $this->accessControl->authorize($user, CurrentActor::token(), PermissionKey::ObjectList->value, $bucket);
    }

    public function view(User $user, StoredObject $object): bool
    {
        return $this->accessControl->authorize($user, CurrentActor::token(), PermissionKey::ObjectRead->value, $object->bucket, $object->key);
    }

    public function upload(User $user, Bucket $bucket, ?string $key = null): bool
    {
        return $this->accessControl->authorize($user, CurrentActor::token(), PermissionKey::ObjectWrite->value, $bucket, $key);
    }

    public function delete(User $user, StoredObject $object): bool
    {
        return $this->accessControl->authorize($user, CurrentActor::token(), PermissionKey::ObjectDelete->value, $object->bucket, $object->key);
    }
}
