<?php

namespace App\Exceptions\Storage;

class ObjectNotFoundException extends \RuntimeException
{
    public static function forKey(string $bucket, string $key): self
    {
        return new self("Object not found: {$bucket}/{$key}");
    }
}
