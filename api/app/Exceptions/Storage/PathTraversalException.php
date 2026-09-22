<?php

namespace App\Exceptions\Storage;

/**
 * Thrown when a bucket or object key would resolve outside the bucket's
 * storage root. Never include the resolved absolute path in the message —
 * it is logged, and may reach clients via the exception handler.
 */
class PathTraversalException extends \RuntimeException
{
    public static function forSegment(string $label): self
    {
        return new self("Invalid {$label}: path traversal or invalid characters detected.");
    }
}
