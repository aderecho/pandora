<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'bucket_id', 'key', 'size_bytes', 'mime_type', 'checksum_sha256', 'etag',
    'current_version_id', 'uploaded_by', 'uploaded_via_token_id', 'metadata',
])]
class StoredObject extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Bucket, $this>
     */
    public function bucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<ApiToken, $this>
     */
    public function uploadToken(): BelongsTo
    {
        return $this->belongsTo(ApiToken::class, 'uploaded_via_token_id');
    }

    /**
     * @return BelongsTo<ObjectVersion, $this>
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ObjectVersion::class, 'current_version_id');
    }

    /**
     * @return HasMany<ObjectVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ObjectVersion::class)->orderByDesc('version_number');
    }

    public function isFolder(): bool
    {
        return str_ends_with($this->key, '/');
    }

    public function basename(): string
    {
        return basename(rtrim($this->key, '/'));
    }
}
