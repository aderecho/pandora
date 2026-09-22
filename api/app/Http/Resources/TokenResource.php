<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ApiToken
 */
class TokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'token_prefix' => $this->token_prefix.'...',
            'owner' => $this->whenLoaded('user', fn () => $this->user?->name),
            'abilities' => $this->abilities,
            'status' => $this->status,
            'expires_at' => $this->expires_at?->toAtomString(),
            'allowed_ips' => $this->allowed_ips,
            'bucket_scopes' => TokenBucketScopeResource::collection($this->whenLoaded('bucketScopes')),
            'last_used_at' => $this->last_used_at?->toAtomString(),
            'last_used_ip' => $this->last_used_ip,
            'created_at' => $this->created_at?->toAtomString(),
        ];
    }
}
