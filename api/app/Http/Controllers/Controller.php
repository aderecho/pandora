<?php

namespace App\Http\Controllers;

use App\Enums\AuditStatus;
use App\Models\Bucket;
use App\Services\AuditLogService;
use App\Support\CurrentActor;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function audit(string $action, ?Bucket $bucket, ?string $key, AuditStatus $status, array $metadata = []): void
    {
        app(AuditLogService::class)->record(
            CurrentActor::actorType(),
            CurrentActor::actorId(),
            $action,
            $bucket?->id,
            $key,
            request()->ip() ?? 'unknown',
            request()->userAgent(),
            $status,
            $metadata,
        );
    }
}
