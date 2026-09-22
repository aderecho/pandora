<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\AuditLog
 */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'actor_type' => $this->actor_type,
            'actor_id' => $this->actor_id,
            'actor' => $this->actorLabel(),
            'action' => $this->action,
            'bucket' => $this->whenLoaded('bucket', fn () => $this->bucket?->name),
            'object_key' => $this->object_key,
            'ip_address' => $this->ip_address,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toAtomString(),
        ];
    }
}
