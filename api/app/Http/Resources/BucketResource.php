<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Bucket
 */
class BucketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'storage_path' => $this->storage_path,
            'driver' => $this->driver,
            'quota_bytes' => $this->quota_bytes,
            'max_file_size_bytes' => $this->max_file_size_bytes,
            'allowed_extensions' => $this->allowed_extensions,
            'allowed_mime_types' => $this->allowed_mime_types,
            'versioning_enabled' => $this->versioning_enabled,
            'public_access' => $this->public_access,
            'status' => $this->status,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
