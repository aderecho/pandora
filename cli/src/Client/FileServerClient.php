<?php

namespace FileServerCli\Client;

use FileServerCli\Client\Exceptions\ApiException;
use FileServerCli\Client\Exceptions\NetworkException;
use FileServerCli\Support\Retry;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;

/**
 * Thin wrapper around the FileServer REST API. Every request/response
 * touching file content uses PHP streams (never (string) buffering) so
 * large uploads/downloads don't blow the CLI's memory limit.
 */
class FileServerClient
{
    private ClientInterface $http;

    public function __construct(string $endpoint, private readonly ?string $token, ?ClientInterface $http = null)
    {
        $this->http = $http ?? new Client([
            'base_uri' => rtrim($endpoint, '/').'/',
            'http_errors' => false,
            'connect_timeout' => 10,
        ]);
    }

    public function health(): array
    {
        return $this->requestJson('GET', 'health', ['headers' => $this->headers(withAuth: false)]);
    }

    public function listBuckets(array $query = []): array
    {
        return $this->requestJson('GET', 'buckets', ['query' => $query, 'headers' => $this->headers()]);
    }

    public function createBucket(string $name, array $options = []): array
    {
        return $this->requestJson('POST', 'buckets', [
            'json' => array_merge(['name' => $name], $options),
            'headers' => $this->headers(),
        ]);
    }

    public function listObjects(string $bucket, string $prefix = '', array $query = []): array
    {
        return $this->requestJson('GET', "buckets/{$bucket}/objects", [
            'query' => array_merge(['prefix' => $prefix], $query),
            'headers' => $this->headers(),
        ]);
    }

    public function objectMetadata(string $bucket, string $key): array
    {
        return $this->requestJson('GET', "buckets/{$bucket}/objects/".$this->encodeKey($key).'/metadata', [
            'headers' => $this->headers(),
        ]);
    }

    public function makeDirectory(string $bucket, string $prefix): array
    {
        return $this->requestJson('POST', "buckets/{$bucket}/objects", [
            'json' => ['key' => rtrim($prefix, '/').'/'],
            'headers' => $this->headers(),
        ]);
    }

    public function deleteObject(string $bucket, string $key): array
    {
        return $this->requestJson('DELETE', "buckets/{$bucket}/objects/".$this->encodeKey($key), [
            'headers' => $this->headers(),
        ]);
    }

    /**
     * @param  resource  $stream  An open, readable stream of the local file.
     */
    public function uploadObject(string $bucket, string $key, mixed $stream, int $sizeBytes, ?callable $onProgress = null): array
    {
        $options = [
            'headers' => $this->headers(),
            'multipart' => [
                ['name' => 'key', 'contents' => $key],
                ['name' => 'file', 'contents' => Utils::streamFor($stream), 'filename' => basename($key)],
            ],
        ];

        if ($onProgress !== null) {
            $options['progress'] = function ($downloadTotal, $downloaded, $uploadTotal, $uploaded) use ($onProgress, $sizeBytes) {
                $onProgress($uploaded > 0 ? $uploaded : 0, $uploadTotal > 0 ? $uploadTotal : $sizeBytes);
            };
        }

        return $this->requestJson('POST', "buckets/{$bucket}/objects", $options);
    }

    /**
     * Streams the response body directly to $destinationPath.
     */
    public function downloadObject(string $bucket, string $key, string $destinationPath, ?callable $onProgress = null): void
    {
        $options = [
            'headers' => $this->headers(),
            'sink' => $destinationPath,
        ];

        if ($onProgress !== null) {
            $options['progress'] = function ($downloadTotal, $downloaded) use ($onProgress) {
                $onProgress($downloaded, $downloadTotal);
            };
        }

        try {
            $response = $this->sendWithRetry('GET', "buckets/{$bucket}/objects/".$this->encodeKey($key), $options);
        } catch (ConnectException $e) {
            throw new NetworkException("Unable to reach the FileServer API: {$e->getMessage()}", previous: $e);
        } catch (RequestException $e) {
            if (is_file($destinationPath)) {
                @unlink($destinationPath);
            }
            if ($e->hasResponse()) {
                $body = json_decode((string) $e->getResponse()->getBody(), true) ?: [];
                throw ApiException::fromResponseBody($e->getResponse()->getStatusCode(), $body);
            }
            throw new NetworkException("Request failed: {$e->getMessage()}", previous: $e);
        }

        if ($response->getStatusCode() >= 400) {
            $body = json_decode((string) $response->getBody(), true) ?: [];
            if (is_file($destinationPath)) {
                @unlink($destinationPath);
            }
            throw ApiException::fromResponseBody($response->getStatusCode(), $body);
        }
    }

    private function encodeKey(string $key): string
    {
        // Percent-encode each path segment individually so slashes in the
        // key survive as path separators (the API route accepts a
        // slash-containing {key} segment).
        return implode('/', array_map('rawurlencode', explode('/', $key)));
    }

    /**
     * @return array<string, mixed>
     */
    private function requestJson(string $method, string $uri, array $options = []): array
    {
        try {
            $response = $this->sendWithRetry($method, $uri, $options);
        } catch (ConnectException $e) {
            throw new NetworkException("Unable to reach the FileServer API: {$e->getMessage()}", previous: $e);
        } catch (RequestException $e) {
            // A retryable status (429/5xx) that never recovered surfaces
            // here as a RequestException carrying its last response — decode
            // it as a normal API error rather than masking it as "network".
            if ($e->hasResponse()) {
                return $this->decode($e->getResponse());
            }
            throw new NetworkException("Request failed: {$e->getMessage()}", previous: $e);
        }

        return $this->decode($response);
    }

    /**
     * The default client uses http_errors => false, so Guzzle itself never
     * throws for a 429/5xx — it just returns the Response. Retry::run only
     * retries on a *thrown* exception, so retryable statuses are turned into
     * a RequestException here to drive the same backoff loop; the last
     * attempt's exception (if retries are exhausted) is rethrown carrying
     * its response for the caller to decode.
     */
    private function sendWithRetry(string $method, string $uri, array $options): ResponseInterface
    {
        return Retry::run(function () use ($method, $uri, $options) {
            $response = $this->http->request($method, $uri, $options);
            $status = $response->getStatusCode();

            if ($status === 429 || $status >= 500) {
                throw new RequestException("Retryable status {$status}", new Request($method, $uri), $response);
            }

            return $response;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $body = json_decode((string) $response->getBody(), true);
        $body = is_array($body) ? $body : [];

        if ($response->getStatusCode() >= 400) {
            throw ApiException::fromResponseBody($response->getStatusCode(), $body);
        }

        return $body;
    }

    /**
     * @return array<string, string>
     */
    private function headers(bool $withAuth = true): array
    {
        $headers = ['Accept' => 'application/json'];

        if ($withAuth && $this->token !== null) {
            $headers['Authorization'] = "Bearer {$this->token}";
        }

        return $headers;
    }
}
