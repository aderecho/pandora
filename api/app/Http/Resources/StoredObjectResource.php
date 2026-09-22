<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\StoredObject
 */
class StoredObjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'file',
            'key' => $this->key,
            'basename' => $this->basename(),
            'size_bytes' => $this->size_bytes,
            'mime_type' => $this->mime_type,
            'checksum_sha256' => $this->checksum_sha256,
            'etag' => $this->etag,
            'uploaded_by' => $this->whenLoaded('uploader', fn () => $this->uploader?->name),
            'uploaded_via_token' => $this->whenLoaded('uploadToken', fn () => $this->uploadToken?->name),
            'metadata' => $this->metadata,
            'versions' => ObjectVersionResource::collection($this->whenLoaded('versions')),
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
