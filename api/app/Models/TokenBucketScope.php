<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['api_token_id', 'bucket_id', 'path_prefix', 'permissions'])]
class TokenBucketScope extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ApiToken, $this>
     */
    public function apiToken(): BelongsTo
    {
        return $this->belongsTo(ApiToken::class);
    }

    /**
     * @return BelongsTo<Bucket, $this>
     */
    public function bucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class);
    }

    public function allowsPath(string $key): bool
    {
        if (empty($this->path_prefix)) {
            return true;
        }

        return str_starts_with($key, $this->path_prefix);
    }

    public function grants(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? [], true);
    }
}
