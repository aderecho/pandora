<?php

namespace App\Models;

use App\Enums\TokenStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'user_id', 'name', 'abilities', 'status', 'expires_at', 'allowed_ips', 'created_by',
])]
#[Hidden(['token_hash'])]
class ApiToken extends Model
{
    use HasFactory;

    public const string TOKEN_PREFIX = 'fsk';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'allowed_ips' => 'array',
            'status' => TokenStatus::class,
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<TokenBucketScope, $this>
     */
    public function bucketScopes(): HasMany
    {
        return $this->hasMany(TokenBucketScope::class);
    }

    /**
     * Generate a new cryptographically-secure raw token string.
     * Format: fsk_<40 random urlsafe chars> — never persisted in plaintext.
     */
    public static function generateRawToken(): string
    {
        return self::TOKEN_PREFIX.'_'.Str::random(48);
    }

    public static function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    public static function prefixFor(string $rawToken): string
    {
        return substr($rawToken, 0, (int) config('fileserver.token_prefix_length', 8));
    }

    public function isActive(): bool
    {
        if ($this->status !== TokenStatus::Active) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function isIpAllowed(?string $ip): bool
    {
        if (empty($this->allowed_ips)) {
            return true;
        }

        if ($ip === null) {
            return false;
        }

        return in_array($ip, $this->allowed_ips, true);
    }

    public function hasAbility(string $ability): bool
    {
        return in_array($ability, $this->abilities ?? [], true);
    }
}
