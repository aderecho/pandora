<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bucket_id', 'user_id', 'can_list', 'can_read', 'can_write', 'can_delete', 'path_prefix'])]
class BucketUserPermission extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'can_list' => 'boolean',
            'can_read' => 'boolean',
            'can_write' => 'boolean',
            'can_delete' => 'boolean',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function allowsPath(string $key): bool
    {
        if (empty($this->path_prefix)) {
            return true;
        }

        return str_starts_with($key, $this->path_prefix);
    }
}
