<?php

namespace App\Models;

use App\Enums\ActorType;
use App\Enums\AuditStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'actor_type', 'actor_id', 'action', 'bucket_id', 'object_key',
    'ip_address', 'user_agent', 'status', 'metadata',
])]
class AuditLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actor_type' => ActorType::class,
            'status' => AuditStatus::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Bucket, $this>
     */
    public function bucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class);
    }

    public function actorLabel(): string
    {
        if ($this->actor_type === ActorType::System || $this->actor_id === null) {
            return 'system';
        }

        return match ($this->actor_type) {
            ActorType::User => User::find($this->actor_id)?->name ?? "user#{$this->actor_id}",
            ActorType::Token => ApiToken::find($this->actor_id)?->name ?? "token#{$this->actor_id}",
            default => (string) $this->actor_id,
        };
    }
}
