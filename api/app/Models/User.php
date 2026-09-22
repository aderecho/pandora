<?php

namespace App\Models;

use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role_id', 'status', 'must_reset_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'must_reset_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return HasMany<ApiToken, $this>
     */
    public function apiTokensIssued(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    /**
     * @return HasMany<BucketUserPermission, $this>
     */
    public function bucketPermissions(): HasMany
    {
        return $this->hasMany(BucketUserPermission::class);
    }

    /**
     * @return HasMany<Bucket, $this>
     */
    public function createdBuckets(): HasMany
    {
        return $this->hasMany(Bucket::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function hasPermission(string $key): bool
    {
        return $this->role !== null && $this->role->hasPermission($key);
    }
}
