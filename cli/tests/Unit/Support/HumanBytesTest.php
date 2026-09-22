<?php

namespace FileServerCli\Tests\Unit\Support;

use FileServerCli\Support\HumanBytes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HumanBytesTest extends TestCase
{
    #[DataProvider('cases')]
    public function test_format(int $bytes, string $expected): void
    {
        $this->assertSame($expected, HumanBytes::format($bytes));
    }

    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function cases(): array
    {
        return [
            'zero' => [0, '0 B'],
            'small' => [512, '512 B'],
            'exactly 1KB' => [1024, '1.00 KB'],
            'kilobytes' => [1536, '1.50 KB'],
            'megabytes' => [5 * 1024 * 1024, '5.00 MB'],
            'gigabytes' => [2 * 1024 * 1024 * 1024, '2.00 GB'],
        ];
    }
}
