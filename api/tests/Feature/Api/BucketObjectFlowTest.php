<?php

namespace Tests\Feature\Api;

use App\Enums\PermissionKey;
use App\Enums\RoleSlug;
use App\Models\ApiToken;
use App\Models\Bucket;
use App\Models\Role;
use App\Models\TokenBucketScope;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BucketObjectFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $storageRoot;

    private string $rawToken;

    private ApiToken $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storageRoot = sys_get_temp_dir().'/fileserver_test_'.uniqid();
        config(['fileserver.storage_root' => $this->storageRoot]);

        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('slug', RoleSlug::StorageAdmin->value)->first();
        $owner = User::factory()->create(['role_id' => $role->id]);

        $this->rawToken = ApiToken::generateRawToken();
        $this->token = ApiToken::factory()->create([
            'user_id' => $owner->id,
            'token_hash' => ApiToken::hashToken($this->rawToken),
            'token_prefix' => ApiToken::prefixFor($this->rawToken),
            'abilities' => PermissionKey::tokenAssignableValues(),
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storageRoot);

        parent::tearDown();
    }

    private function auth(): static
    {
        return $this->withHeader('Authorization', "Bearer {$this->rawToken}");
    }

    private function grantBucketScope(Bucket $bucket): void
    {
        TokenBucketScope::factory()->create([
            'api_token_id' => $this->token->id,
            'bucket_id' => $bucket->id,
            'permissions' => PermissionKey::tokenAssignableValues(),
        ]);
    }

    public function test_full_bucket_and_object_lifecycle(): void
    {
        // Create bucket
        $response = $this->auth()->postJson('/api/v1/buckets', ['name' => 'test-bucket']);
        $response->assertStatus(201)->assertJsonPath('data.name', 'test-bucket');

        $bucket = Bucket::where('name', 'test-bucket')->firstOrFail();
        $this->assertTrue(is_dir($this->storageRoot.'/test-bucket'));

        $this->grantBucketScope($bucket);

        // Upload (createWithContent so the file has real bytes on disk —
        // plain fake()->create() only fakes UploadedFile::getSize() for
        // validation tests and writes a genuinely empty temp file).
        $content = str_repeat('report content ', 341); // 5115 bytes
        $file = UploadedFile::fake()->createWithContent('report.txt', $content);
        $response = $this->auth()->post('/api/v1/buckets/test-bucket/objects', [
            'key' => 'reports/2026/q3.txt',
            'file' => $file,
        ]);
        $response->assertStatus(201)->assertJsonPath('data.key', 'reports/2026/q3.txt');
        $this->assertTrue(is_file($this->storageRoot.'/test-bucket/reports/2026/q3.txt'));

        // Download
        $download = $this->auth()->get('/api/v1/buckets/test-bucket/objects/reports/2026/q3.txt');
        $download->assertStatus(200);
        $this->assertSame(strlen($content), (int) $download->headers->get('Content-Length'));
        $this->assertSame($content, $download->streamedContent());

        // Metadata
        $meta = $this->auth()->getJson('/api/v1/buckets/test-bucket/objects/reports/2026/q3.txt/metadata');
        $meta->assertStatus(200)
            ->assertJsonPath('data.key', 'reports/2026/q3.txt')
            ->assertJsonPath('data.mime_type', 'text/plain');

        // mkdir (empty folder marker)
        $mkdir = $this->auth()->postJson('/api/v1/buckets/test-bucket/objects', ['key' => 'archive/']);
        $mkdir->assertStatus(201)->assertJsonPath('data.type', 'file');
        $this->assertTrue(is_dir($this->storageRoot.'/test-bucket/archive'));

        // List root: should show "reports/" and "archive/" folders
        $list = $this->auth()->getJson('/api/v1/buckets/test-bucket/objects?prefix=');
        $list->assertStatus(200);
        $keys = collect($list->json('data'))->pluck('key')->all();
        $this->assertContains('reports/', $keys);
        $this->assertContains('archive/', $keys);

        // Presigned download
        $presign = $this->auth()->postJson('/api/v1/buckets/test-bucket/objects/reports/2026/q3.txt/presigned-download');
        $presign->assertStatus(200);
        $url = $presign->json('data.url');
        $path = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);
        $publicDownload = $this->get($path); // no auth header — signature only
        $publicDownload->assertStatus(200);

        // Presigned upload
        $presignUpload = $this->auth()->postJson('/api/v1/buckets/test-bucket/presigned-upload', [
            'key' => 'incoming/new-file.bin',
        ]);
        $presignUpload->assertStatus(200);
        $uploadUrl = $presignUpload->json('data.url');
        $uploadPath = parse_url($uploadUrl, PHP_URL_PATH).'?'.parse_url($uploadUrl, PHP_URL_QUERY);
        $rawUpload = $this->call('PUT', $uploadPath, content: str_repeat('x', 2048));
        $rawUpload->assertStatus(201);
        $this->assertTrue(is_file($this->storageRoot.'/test-bucket/incoming/new-file.bin'));

        // Delete original object
        $delete = $this->auth()->deleteJson('/api/v1/buckets/test-bucket/objects/reports/2026/q3.txt');
        $delete->assertStatus(200);
        $this->assertFalse(is_file($this->storageRoot.'/test-bucket/reports/2026/q3.txt'));

        $afterDelete = $this->auth()->getJson('/api/v1/buckets/test-bucket/objects/reports/2026/q3.txt/metadata');
        $afterDelete->assertStatus(404)->assertJsonPath('error.code', 'OBJECT_NOT_FOUND');

        // Update bucket config
        $update = $this->auth()->patchJson('/api/v1/buckets/test-bucket', ['quota_bytes' => 123456]);
        $update->assertStatus(200)->assertJsonPath('data.quota_bytes', 123456);

        // Delete bucket
        $destroy = $this->auth()->deleteJson('/api/v1/buckets/test-bucket');
        $destroy->assertStatus(200);
        $this->assertSoftDeleted('buckets', ['name' => 'test-bucket']);
    }

    public function test_quota_is_enforced(): void
    {
        $this->auth()->postJson('/api/v1/buckets', [
            'name' => 'tiny-bucket',
            'quota_bytes' => 1024,
        ])->assertStatus(201);

        $bucket = Bucket::where('name', 'tiny-bucket')->firstOrFail();
        $this->grantBucketScope($bucket);

        $file = UploadedFile::fake()->create('big.bin', 10, 'application/octet-stream'); // 10KB > 1KB quota

        $this->auth()->post('/api/v1/buckets/tiny-bucket/objects', [
            'key' => 'big.bin',
            'file' => $file,
        ])->assertStatus(413)->assertJsonPath('error.code', 'QUOTA_EXCEEDED');
    }

    public function test_disallowed_extension_is_rejected(): void
    {
        $this->auth()->postJson('/api/v1/buckets', [
            'name' => 'pdf-only',
            'allowed_extensions' => ['pdf'],
        ])->assertStatus(201);

        $bucket = Bucket::where('name', 'pdf-only')->firstOrFail();
        $this->grantBucketScope($bucket);

        $file = UploadedFile::fake()->create('notes.txt', 1, 'text/plain');

        $this->auth()->post('/api/v1/buckets/pdf-only/objects', [
            'key' => 'notes.txt',
            'file' => $file,
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_versioning_keeps_history(): void
    {
        $this->auth()->postJson('/api/v1/buckets', [
            'name' => 'versioned',
            'versioning_enabled' => true,
        ])->assertStatus(201);

        $bucket = Bucket::where('name', 'versioned')->firstOrFail();
        $this->grantBucketScope($bucket);

        foreach (['v1 content', 'v2 content longer'] as $content) {
            $file = UploadedFile::fake()->createWithContent('doc.txt', $content);
            $this->auth()->post('/api/v1/buckets/versioned/objects', [
                'key' => 'doc.txt',
                'file' => $file,
            ])->assertStatus(201);
        }

        $meta = $this->auth()->getJson('/api/v1/buckets/versioned/objects/doc.txt/metadata');
        $meta->assertStatus(200);
        $this->assertCount(2, $meta->json('data.versions'));
    }
}
