<?php

namespace FileServerCli\Support;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;

/**
 * Retries a callable on connection failures, 5xx responses, and 429s, with
 * increasing backoff. Anything else (4xx other than 429, or a non-Guzzle
 * exception) is rethrown immediately — those aren't transient.
 */
final class Retry
{
    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @param  array<int, int>  $delaysMs
     * @return T
     */
    public static function run(callable $operation, int $attempts = 3, array $delaysMs = [200, 500, 1000]): mixed
    {
        $lastException = null;

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            try {
                return $operation();
            } catch (ConnectException $e) {
                $lastException = $e;
            } catch (RequestException $e) {
                $status = $e->getResponse()?->getStatusCode();

                if ($status === null || $status === 429 || $status >= 500) {
                    $lastException = $e;
                } else {
                    throw $e;
                }
            }

            if ($attempt < $attempts - 1) {
                $delay = $delaysMs[$attempt] ?? end($delaysMs);
                usleep($delay * 1000);
            }
        }

        throw $lastException;
    }

    private function __construct() {}
}
