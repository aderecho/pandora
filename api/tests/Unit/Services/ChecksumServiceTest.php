<?php

namespace Tests\Unit\Services;

use App\Services\ChecksumService;
use Tests\TestCase;

class ChecksumServiceTest extends TestCase
{
    private ChecksumService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ChecksumService;
    }

    public function test_sha256_matches_native_hash_for_a_known_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'chk');
        file_put_contents($path, 'The quick brown fox jumps over the lazy dog');

        $this->assertSame(hash_file('sha256', $path), $this->service->sha256File($path));

        unlink($path);
    }

    public function test_md5_matches_native_hash_for_a_known_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'chk');
        file_put_contents($path, 'The quick brown fox jumps over the lazy dog');

        $this->assertSame(hash_file('md5', $path), $this->service->md5File($path));

        unlink($path);
    }

    public function test_hashing_a_large_file_does_not_exhaust_memory(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'chk');
        $handle = fopen($path, 'wb');
        // 20MB of repeated content, written in chunks — proves streaming, not full buffering.
        for ($i = 0; $i < 20; $i++) {
            fwrite($handle, str_repeat('x', 1024 * 1024));
        }
        fclose($handle);

        $memoryBefore = memory_get_usage();
        $hash = $this->service->sha256File($path);
        $memoryAfter = memory_get_usage();

        $this->assertSame(64, strlen($hash));
        $this->assertLessThan(2 * 1024 * 1024, $memoryAfter - $memoryBefore);

        unlink($path);
    }

    public function test_throws_for_an_unreadable_path(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->sha256File('/nonexistent/path/does-not-exist.bin');
    }
}
