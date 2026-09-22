<?php

namespace App\Jobs;

use App\Enums\ActorType;
use App\Enums\AuditStatus;
use App\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WriteAuditLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly ActorType $actorType,
        public readonly ?int $actorId,
        public readonly string $action,
        public readonly ?int $bucketId,
        public readonly ?string $objectKey,
        public readonly string $ipAddress,
        public readonly ?string $userAgent,
        public readonly AuditStatus $status,
        public readonly array $metadata = [],
    ) {}

    public function handle(): void
    {
        AuditLog::query()->create([
            'actor_type' => $this->actorType,
            'actor_id' => $this->actorId,
            'action' => $this->action,
            'bucket_id' => $this->bucketId,
            'object_key' => $this->objectKey,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'status' => $this->status,
            'metadata' => $this->metadata,
        ]);
    }
}
