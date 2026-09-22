<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->whenLoaded('role', fn () => [
                'id' => $this->role->id,
                'name' => $this->role->name,
                'slug' => $this->role->slug,
            ]),
            'status' => $this->status,
            'must_reset_password' => $this->must_reset_password,
            'last_login_at' => $this->last_login_at?->toAtomString(),
            'last_login_ip' => $this->last_login_ip,
            'created_at' => $this->created_at?->toAtomString(),
        ];
    }
}
