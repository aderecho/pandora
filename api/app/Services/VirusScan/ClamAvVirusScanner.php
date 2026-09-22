<?php

namespace App\Services\VirusScan;

use Illuminate\Support\Facades\Log;

/**
 * Scans files via clamd's INSTREAM protocol over TCP. Streams the file in
 * chunks rather than handing clamd a path, so it works even when clamd runs
 * as a different user or inside its own chroot/container.
 */
class ClamAvVirusScanner implements VirusScannerInterface
{
    private const int CHUNK_SIZE = 65536;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly int $timeout,
        private readonly bool $enabled,
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function scan(string $absolutePath): ScanResult
    {
        $socket = @stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            $this->timeout
        );

        if ($socket === false) {
            Log::warning('ClamAV connection failed; treating file as unscanned/clean.', [
                'host' => $this->host,
                'port' => $this->port,
                'error' => $errstr,
            ]);

            return ScanResult::clean();
        }

        stream_set_timeout($socket, $this->timeout);

        try {
            fwrite($socket, "zINSTREAM\0");

            $handle = fopen($absolutePath, 'rb');
            if ($handle === false) {
                throw new \RuntimeException("Unable to open file for scanning: {$absolutePath}");
            }

            try {
                while (! feof($handle)) {
                    $chunk = fread($handle, self::CHUNK_SIZE);
                    if ($chunk === false || $chunk === '') {
                        break;
                    }
                    fwrite($socket, pack('N', strlen($chunk)).$chunk);
                }
            } finally {
                fclose($handle);
            }

            // Zero-length chunk signals end of stream.
            fwrite($socket, pack('N', 0));

            $response = trim((string) fgets($socket, 4096));
        } finally {
            fclose($socket);
        }

        // Expected forms: "stream: OK" or "stream: <Signature> FOUND"
        if (str_contains($response, 'FOUND')) {
            preg_match('/stream:\s*(.+)\s+FOUND/', $response, $matches);

            return ScanResult::infected($matches[1] ?? 'unknown-signature');
        }

        return ScanResult::clean();
    }
}
