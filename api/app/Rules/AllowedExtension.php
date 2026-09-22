<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AllowedExtension implements ValidationRule
{
    /**
     * @param  array<int, string>|null  $allowed
     */
    public function __construct(private readonly ?array $allowed, private readonly string $key)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($this->allowed)) {
            return;
        }

        // Checked against the destination key, not the client's original
        // filename — the object's own name is what actually matters.
        $extension = strtolower(pathinfo($this->key, PATHINFO_EXTENSION));
        $allowed = array_map('strtolower', $this->allowed);

        if (! in_array($extension, $allowed, true)) {
            $fail("The object key extension [.{$extension}] is not allowed for this bucket.");
        }
    }
}
