<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Enums\AuditStatus;
use App\Jobs\WriteAuditLog;

class AuditLogService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        ActorType $actorType,
        ?int $actorId,
        string $action,
        ?int $bucketId,
        ?string $objectKey,
        string $ipAddress,
        ?string $userAgent,
        AuditStatus $status,
        array $metadata = [],
    ): void {
        WriteAuditLog::dispatch(
            $actorType,
            $actorId,
            $action,
            $bucketId,
            $objectKey,
            $ipAddress,
            $userAgent,
            $status,
            $metadata,
        );
    }
}
