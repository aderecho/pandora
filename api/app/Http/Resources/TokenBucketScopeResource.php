<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TokenBucketScope
 */
class TokenBucketScopeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'bucket' => $this->whenLoaded('bucket', fn () => $this->bucket->name),
            'path_prefix' => $this->path_prefix,
            'permissions' => $this->permissions,
        ];
    }
}
