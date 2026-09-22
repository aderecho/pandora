<?php

namespace FileServerCli\Tests\Unit\Config;

use FileServerCli\Config\ConfigRepository;
use PHPUnit\Framework\TestCase;

class ConfigRepositoryTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/fileserver_cli_test_'.uniqid().'/config.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
        if (is_dir(dirname($this->path))) {
            rmdir(dirname($this->path));
        }
    }

    public function test_set_and_get_round_trip(): void
    {
        $config = new ConfigRepository($this->path);
        $config->set('endpoint', 'https://files.example.com/api/v1');

        $reloaded = new ConfigRepository($this->path);
        $this->assertSame('https://files.example.com/api/v1', $reloaded->get('endpoint'));
    }

    public function test_get_returns_null_for_unset_key(): void
    {
        $config = new ConfigRepository($this->path);
        $this->assertNull($config->get('token'));
    }

    public function test_rejects_unknown_keys(): void
    {
        $config = new ConfigRepository($this->path);

        $this->expectException(\InvalidArgumentException::class);
        $config->set('not-a-real-key', 'value');
    }

    public function test_config_file_is_created_with_restrictive_permissions(): void
    {
        $config = new ConfigRepository($this->path);
        $config->set('token', 'secret-value');

        $perms = fileperms($this->path) & 0777;
        $this->assertSame(0600, $perms);
    }

    public function test_updates_preserve_other_keys(): void
    {
        $config = new ConfigRepository($this->path);
        $config->set('endpoint', 'https://a.example.com/api/v1');
        $config->set('token', 'abc');

        $reloaded = new ConfigRepository($this->path);
        $this->assertSame('https://a.example.com/api/v1', $reloaded->get('endpoint'));
        $this->assertSame('abc', $reloaded->get('token'));
    }
}
