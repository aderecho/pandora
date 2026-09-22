<?php

namespace App\Events;

use App\Enums\ActorType;

/**
 * Fired by ScopedPermission whenever an authenticated actor is denied a
 * bucket/object action. A listener persists these to audit_logs (status
 * "failure") once AuditLogService exists — see Phase 5.
 */
class AccessDenied
{
    public function __construct(
        public readonly ActorType $actorType,
        public readonly ?int $actorId,
        public readonly string $permission,
        public readonly ?int $bucketId,
        public readonly ?string $objectKey,
        public readonly string $ipAddress,
        public readonly ?string $userAgent,
    ) {}
}
