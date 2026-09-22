<?php

namespace App\Listeners;

use App\Enums\AuditStatus;
use App\Events\AccessDenied;
use App\Services\AuditLogService;

class LogAccessDenied
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function handle(AccessDenied $event): void
    {
        $this->auditLog->record(
            $event->actorType,
            $event->actorId,
            "access.denied:{$event->permission}",
            $event->bucketId,
            $event->objectKey,
            $event->ipAddress,
            $event->userAgent,
            AuditStatus::Failure,
        );
    }
}
