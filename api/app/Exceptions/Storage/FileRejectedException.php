<?php

namespace App\Exceptions\Storage;

/**
 * Thrown when an upload fails a bucket-level policy check (disallowed
 * extension/MIME type, oversized file, or a failed virus scan) rather than
 * a path-safety or quota check.
 */
class FileRejectedException extends \RuntimeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
