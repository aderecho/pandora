<?php

namespace App\Services\VirusScan;

interface VirusScannerInterface
{
    /**
     * Scan a file already on local disk and report whether it's clean.
     * Implementations must never throw for "infected" — that's a normal,
     * expected result communicated via ScanResult::infected().
     */
    public function scan(string $absolutePath): ScanResult;

    public function isEnabled(): bool;
}
