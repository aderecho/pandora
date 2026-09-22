<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'stored_object_id', 'version_number', 'size_bytes', 'checksum_sha256',
    'storage_relative_path', 'uploaded_by', 'is_current',
])]
class ObjectVersion extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'is_current' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<StoredObject, $this>
     */
    public function storedObject(): BelongsTo
    {
        return $this->belongsTo(StoredObject::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
