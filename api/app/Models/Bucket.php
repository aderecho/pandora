<?php

namespace App\Models;

use App\Enums\BucketStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name', 'slug', 'storage_path', 'driver', 'quota_bytes', 'max_file_size_bytes',
    'allowed_extensions', 'allowed_mime_types', 'versioning_enabled', 'public_access',
    'status', 'created_by',
])]
class Bucket extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quota_bytes' => 'integer',
            'max_file_size_bytes' => 'integer',
            'allowed_extensions' => 'array',
            'allowed_mime_types' => 'array',
            'versioning_enabled' => 'boolean',
            'public_access' => 'boolean',
            'status' => BucketStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<BucketUserPermission, $this>
     */
    public function userPermissions(): HasMany
    {
        return $this->hasMany(BucketUserPermission::class);
    }

    /**
     * @return HasMany<TokenBucketScope, $this>
     */
    public function tokenScopes(): HasMany
    {
        return $this->hasMany(TokenBucketScope::class);
    }

    /**
     * @return HasMany<StoredObject, $this>
     */
    public function objects(): HasMany
    {
        return $this->hasMany(StoredObject::class);
    }

    public function isActive(): bool
    {
        return $this->status === BucketStatus::Active;
    }

    public function isExtensionAllowed(string $extension): bool
    {
        if (empty($this->allowed_extensions)) {
            return true;
        }

        return in_array(strtolower(ltrim($extension, '.')), array_map('strtolower', $this->allowed_extensions), true);
    }

    public function isMimeTypeAllowed(string $mimeType): bool
    {
        if (empty($this->allowed_mime_types)) {
            return true;
        }

        return in_array($mimeType, $this->allowed_mime_types, true);
    }
}
