<?php

namespace Tests\Unit\Services;

use App\Models\Bucket;
use App\Services\PresignedUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresignedUrlServiceTest extends TestCase
{
    use RefreshDatabase;

    private PresignedUrlService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PresignedUrlService('unit-test-secret-key');
    }

    public function test_a_freshly_generated_download_url_verifies_successfully(): void
    {
        $bucket = Bucket::factory()->create(['name' => 'docs']);

        $result = $this->service->forDownload($bucket, 'reports/q3.txt', 300);

        parse_str((string) parse_url($result['url'], PHP_URL_QUERY), $query);

        $this->assertTrue($this->service->verify(
            'GET', $query['bucket'], $query['key'], (int) $query['expires'], null, $query['signature']
        ));
    }

    public function test_an_expired_url_fails_verification(): void
    {
        $bucket = Bucket::factory()->create(['name' => 'docs']);
        $result = $this->service->forDownload($bucket, 'old.txt', -10); // already expired

        parse_str((string) parse_url($result['url'], PHP_URL_QUERY), $query);

        $this->assertFalse($this->service->verify(
            'GET', $query['bucket'], $query['key'], (int) $query['expires'], null, $query['signature']
        ));
    }

    public function test_signature_does_not_verify_for_a_different_key(): void
    {
        $bucket = Bucket::factory()->create(['name' => 'docs']);
        $result = $this->service->forUpload($bucket, 'a.txt', null, 300);

        parse_str((string) parse_url($result['url'], PHP_URL_QUERY), $query);

        $this->assertFalse($this->service->verify(
            'PUT', $query['bucket'], 'b.txt', (int) $query['expires'], null, $query['signature']
        ));
    }

    public function test_signature_does_not_verify_for_a_different_verb(): void
    {
        $bucket = Bucket::factory()->create(['name' => 'docs']);
        $result = $this->service->forDownload($bucket, 'a.txt', 300);

        parse_str((string) parse_url($result['url'], PHP_URL_QUERY), $query);

        $this->assertFalse($this->service->verify(
            'PUT', $query['bucket'], $query['key'], (int) $query['expires'], null, $query['signature']
        ));
    }

    public function test_upload_signature_embeds_max_size_and_rejects_a_tampered_limit(): void
    {
        $bucket = Bucket::factory()->create(['name' => 'docs']);
        $result = $this->service->forUpload($bucket, 'a.txt', 1000, 300);

        parse_str((string) parse_url($result['url'], PHP_URL_QUERY), $query);

        // Correct max_size verifies...
        $this->assertTrue($this->service->verify(
            'PUT', $query['bucket'], $query['key'], (int) $query['expires'], 1000, $query['signature']
        ));

        // ...but a client-tampered, larger max_size does not.
        $this->assertFalse($this->service->verify(
            'PUT', $query['bucket'], $query['key'], (int) $query['expires'], 999999, $query['signature']
        ));
    }

    public function test_signature_is_invalid_when_tampered(): void
    {
        $bucket = Bucket::factory()->create(['name' => 'docs']);
        $result = $this->service->forDownload($bucket, 'a.txt', 300);

        parse_str((string) parse_url($result['url'], PHP_URL_QUERY), $query);

        $this->assertFalse($this->service->verify(
            'GET', $query['bucket'], $query['key'], (int) $query['expires'], null, 'not-the-real-signature'
        ));
    }
}
