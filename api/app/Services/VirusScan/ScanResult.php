<?php

namespace App\Services\VirusScan;

final readonly class ScanResult
{
    private function __construct(
        public bool $clean,
        public ?string $signature = null,
    ) {}

    public static function clean(): self
    {
        return new self(clean: true);
    }

    public static function infected(string $signature): self
    {
        return new self(clean: false, signature: $signature);
    }
}
