<?php

namespace FileServerCli\Tests\Unit\Support;

use FileServerCli\Support\Retry;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class RetryTest extends TestCase
{
    public function test_succeeds_immediately_without_retrying(): void
    {
        $calls = 0;

        $result = Retry::run(function () use (&$calls) {
            $calls++;

            return 'ok';
        }, attempts: 3, delaysMs: [1, 1, 1]);

        $this->assertSame('ok', $result);
        $this->assertSame(1, $calls);
    }

    public function test_retries_on_connect_exception_then_succeeds(): void
    {
        $calls = 0;
        $request = new Request('GET', '/');

        $result = Retry::run(function () use (&$calls, $request) {
            $calls++;
            if ($calls < 3) {
                throw new ConnectException('connection refused', $request);
            }

            return 'recovered';
        }, attempts: 3, delaysMs: [1, 1, 1]);

        $this->assertSame('recovered', $result);
        $this->assertSame(3, $calls);
    }

    public function test_retries_on_5xx_then_gives_up_after_max_attempts(): void
    {
        $calls = 0;
        $request = new Request('GET', '/');

        $this->expectException(ServerException::class);

        try {
            Retry::run(function () use (&$calls, $request) {
                $calls++;
                throw new ServerException('boom', $request, new Response(500));
            }, attempts: 3, delaysMs: [1, 1, 1]);
        } finally {
            $this->assertSame(3, $calls);
        }
    }

    public function test_does_not_retry_a_plain_4xx(): void
    {
        $calls = 0;
        $request = new Request('GET', '/');

        $this->expectException(\GuzzleHttp\Exception\ClientException::class);

        try {
            Retry::run(function () use (&$calls, $request) {
                $calls++;
                throw new \GuzzleHttp\Exception\ClientException('nope', $request, new Response(422));
            }, attempts: 3, delaysMs: [1, 1, 1]);
        } finally {
            $this->assertSame(1, $calls);
        }
    }

    public function test_retries_a_429(): void
    {
        $calls = 0;
        $request = new Request('GET', '/');

        $result = Retry::run(function () use (&$calls, $request) {
            $calls++;
            if ($calls < 2) {
                throw new \GuzzleHttp\Exception\ClientException('rate limited', $request, new Response(429));
            }

            return 'ok';
        }, attempts: 3, delaysMs: [1, 1, 1]);

        $this->assertSame('ok', $result);
        $this->assertSame(2, $calls);
    }
}
