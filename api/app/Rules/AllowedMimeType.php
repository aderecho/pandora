<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class AllowedMimeType implements ValidationRule
{
    /**
     * @param  array<int, string>|null  $allowed
     */
    public function __construct(private readonly ?array $allowed)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($this->allowed) || ! $value instanceof UploadedFile) {
            return;
        }

        $mimeType = $value->getMimeType();

        if (! in_array($mimeType, $this->allowed, true)) {
            $fail("The :attribute MIME type [{$mimeType}] is not allowed for this bucket.");
        }
    }
}
