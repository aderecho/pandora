<?php

namespace Tests\Unit\Services\Storage;

use App\Exceptions\Storage\ObjectNotFoundException;
use App\Exceptions\Storage\PathTraversalException;
use App\Services\ChecksumService;
use App\Services\Storage\LocalStorageDriver;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LocalStorageDriverTest extends TestCase
{
    private string $root;

    private LocalStorageDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/fs_driver_'.uniqid();
        File::ensureDirectoryExists($this->root);
        $this->driver = new LocalStorageDriver($this->root, new ChecksumService);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_put_writes_a_file_and_returns_accurate_metadata(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'src');
        file_put_contents($tmp, 'hello world');

        $object = $this->driver->put('bucket-a', 'docs/readme.txt', $tmp);

        $this->assertSame(11, $object->sizeBytes);
        $this->assertSame(hash('sha256', 'hello world'), $object->checksumSha256);
        $this->assertTrue(is_file($this->root.'/bucket-a/docs/readme.txt'));

        unlink($tmp);
    }

    public function test_put_accepts_a_resource_stream(): void
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'stream content');
        rewind($stream);

        $object = $this->driver->put('bucket-a', 'from-stream.txt', $stream);
        fclose($stream);

        $this->assertSame(14, $object->sizeBytes);
        $this->assertSame('stream content', file_get_contents($this->root.'/bucket-a/from-stream.txt'));
    }

    public function test_get_streams_the_correct_content_and_headers(): void
    {
        $this->driver->put('bucket-a', 'x.txt', $this->tmpFileWith('payload'));

        $response = $this->driver->get('bucket-a', 'x.txt');

        $this->assertSame('7', $response->headers->get('Content-Length'));
        $this->assertStringContainsString('x.txt', $response->headers->get('Content-Disposition'));
    }

    public function test_get_throws_when_object_is_missing(): void
    {
        $this->expectException(ObjectNotFoundException::class);
        $this->driver->get('bucket-a', 'nope.txt');
    }

    public function test_exists_reflects_real_filesystem_state(): void
    {
        $this->assertFalse($this->driver->exists('bucket-a', 'a.txt'));
        $this->driver->put('bucket-a', 'a.txt', $this->tmpFileWith('x'));
        $this->assertTrue($this->driver->exists('bucket-a', 'a.txt'));
    }

    public function test_delete_removes_the_file_and_prunes_now_empty_directories(): void
    {
        $this->driver->put('bucket-a', 'nested/deep/file.txt', $this->tmpFileWith('x'));
        $this->assertTrue($this->driver->delete('bucket-a', 'nested/deep/file.txt'));
        $this->assertFalse(is_file($this->root.'/bucket-a/nested/deep/file.txt'));
        $this->assertFalse(is_dir($this->root.'/bucket-a/nested/deep'));
        $this->assertFalse(is_dir($this->root.'/bucket-a/nested'));
    }

    public function test_delete_returns_false_for_a_missing_file(): void
    {
        $this->assertFalse($this->driver->delete('bucket-a', 'missing.txt'));
    }

    public function test_make_directory_creates_a_real_directory(): void
    {
        $this->assertTrue($this->driver->makeDirectory('bucket-a', 'archive/2026'));
        $this->assertTrue(is_dir($this->root.'/bucket-a/archive/2026'));
    }

    public function test_list_returns_folders_before_files_sorted_by_key(): void
    {
        $this->driver->put('bucket-a', 'b-file.txt', $this->tmpFileWith('x'));
        $this->driver->makeDirectory('bucket-a', 'a-folder');
        $this->driver->put('bucket-a', 'z-file.txt', $this->tmpFileWith('x'));

        $entries = $this->driver->list('bucket-a');

        $this->assertSame(['a-folder/', 'b-file.txt', 'z-file.txt'], array_map(fn ($e) => $e->key, $entries));
        $this->assertTrue($entries[0]->isDirectory);
    }

    public function test_list_returns_empty_array_for_nonexistent_prefix(): void
    {
        $this->assertSame([], $this->driver->list('bucket-a', 'does/not/exist'));
    }

    #[DataProvider('traversalAttempts')]
    public function test_path_traversal_is_rejected(string $bucket, string $key): void
    {
        $this->expectException(PathTraversalException::class);
        $this->driver->exists($bucket, $key);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function traversalAttempts(): array
    {
        return [
            'dotdot key' => ['bucket-a', '../../etc/passwd'],
            'dotdot in middle' => ['bucket-a', 'foo/../../bar'],
            'absolute key' => ['bucket-a', '/etc/passwd'],
            'null byte' => ['bucket-a', "evil\0.txt"],
            'dotdot bucket' => ['../outside', 'file.txt'],
            'absolute bucket' => ['/etc', 'file.txt'],
            'empty key' => ['bucket-a', ''],
        ];
    }

    public function test_symlink_escape_is_blocked_by_realpath_containment_check(): void
    {
        $outside = sys_get_temp_dir().'/fs_outside_'.uniqid();
        File::ensureDirectoryExists($outside);
        file_put_contents($outside.'/secret.txt', 'secret');

        File::ensureDirectoryExists($this->root.'/bucket-a');
        symlink($outside, $this->root.'/bucket-a/escape');

        $this->expectException(PathTraversalException::class);

        try {
            $this->driver->exists('bucket-a', 'escape/secret.txt');
        } finally {
            File::deleteDirectory($outside);
        }
    }

    private function tmpFileWith(string $content): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'src');
        file_put_contents($tmp, $content);

        return $tmp;
    }
}
