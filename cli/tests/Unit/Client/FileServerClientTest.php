<?php

namespace FileServerCli\Tests\Unit\Client;

use FileServerCli\Client\Exceptions\ApiException;
use FileServerCli\Client\Exceptions\NetworkException;
use FileServerCli\Client\FileServerClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class FileServerClientTest extends TestCase
{
    /**
     * @param  array<int, Response|\Throwable>  $responses
     * @param  array<int, array<string, mixed>>|null  $history  Out-parameter: filled with each transaction.
     */
    private function makeClient(array $responses, ?array &$history = null): FileServerClient
    {
        $history = [];
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        // http_errors => false mirrors FileServerClient's own default client
        // (see decode()) so 4xx/5xx come back as ordinary Responses.
        $http = new Client(['handler' => $stack, 'base_uri' => 'https://files.example.com/api/v1/', 'http_errors' => false]);

        return new FileServerClient('https://files.example.com/api/v1', 'fsk_test', $http);
    }

    public function test_list_buckets_sends_bearer_token_and_decodes_data(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode(['data' => [['name' => 'documents']], 'error' => null])),
        ], $history);

        $result = $client->listBuckets();

        $this->assertSame('documents', $result['data'][0]['name']);
        $this->assertSame('Bearer fsk_test', $history[0]['request']->getHeaderLine('Authorization'));
    }

    public function test_a_4xx_response_throws_api_exception_with_code_and_message(): void
    {
        $client = $this->makeClient([
            new Response(422, [], json_encode([
                'data' => null,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'The name field is required.'],
            ])),
        ]);

        try {
            $client->createBucket('');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(422, $e->statusCode);
            $this->assertSame('VALIDATION_ERROR', $e->errorCode);
            $this->assertSame('The name field is required.', $e->getMessage());
        }
    }

    public function test_a_connection_failure_is_wrapped_as_network_exception(): void
    {
        // Retry::run's default 3 attempts each need a queued failure.
        $client = $this->makeClient([
            new ConnectException('Connection refused', new Request('GET', 'buckets')),
            new ConnectException('Connection refused', new Request('GET', 'buckets')),
            new ConnectException('Connection refused', new Request('GET', 'buckets')),
        ]);

        $this->expectException(NetworkException::class);
        $client->listBuckets();
    }

    public function test_transient_5xx_is_retried_and_recovers(): void
    {
        $client = $this->makeClient([
            new Response(503),
            new Response(200, [], json_encode(['data' => [], 'error' => null])),
        ]);

        $result = $client->listBuckets();

        $this->assertSame([], $result['data']);
    }

    public function test_object_key_with_slashes_is_percent_encoded_segment_by_segment(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode(['data' => ['key' => 'a/b c.txt'], 'error' => null])),
        ], $history);

        $client->objectMetadata('docs', 'a/b c.txt');

        $this->assertSame('/api/v1/buckets/docs/objects/a/b%20c.txt/metadata', $history[0]['request']->getUri()->getPath());
    }

    public function test_health_check_omits_authorization_header(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode(['data' => ['status' => 'ok'], 'error' => null])),
        ], $history);

        $client->health();

        $this->assertFalse($history[0]['request']->hasHeader('Authorization'));
    }
}
