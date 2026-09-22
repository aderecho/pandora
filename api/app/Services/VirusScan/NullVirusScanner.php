<?php

namespace App\Services\VirusScan;

/**
 * Default scanner used when ClamAV isn't configured. Always reports clean
 * so uploads aren't blocked in environments without ClamAV installed, while
 * keeping the VirusScannerInterface seam in place for when it is.
 */
class NullVirusScanner implements VirusScannerInterface
{
    public function scan(string $absolutePath): ScanResult
    {
        return ScanResult::clean();
    }

    public function isEnabled(): bool
    {
        return false;
    }
}
