<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates an object key/prefix at the HTTP boundary, mirroring the
 * lexical checks LocalStorageDriver enforces again at the storage layer
 * (defense in depth — this rule gives a friendly 422 instead of a 500).
 */
class SafeObjectKey implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('The :attribute is required.');

            return;
        }

        if (str_contains($value, "\0")) {
            $fail('The :attribute contains invalid characters.');

            return;
        }

        if (strlen($value) > 1024) {
            $fail('The :attribute may not be greater than 1024 characters.');

            return;
        }

        if (str_starts_with($value, '/') || str_starts_with($value, '\\')) {
            $fail('The :attribute may not start with a slash.');

            return;
        }

        foreach (explode('/', str_replace('\\', '/', $value)) as $segment) {
            if ($segment === '..' || $segment === '.') {
                $fail('The :attribute may not contain path traversal segments.');

                return;
            }
        }
    }
}
