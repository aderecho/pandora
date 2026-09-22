<?php

namespace FileServerCli\Tests\Unit\Config;

use FileServerCli\Config\ConfigRepository;
use FileServerCli\Config\Settings;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/fileserver_cli_settings_'.uniqid().'/config.json';
    }

    protected function tearDown(): void
    {
        putenv('FILESERVER_ENDPOINT');
        putenv('FILESERVER_TOKEN');
        putenv('FILESERVER_BUCKET');

        if (is_file($this->path)) {
            unlink($this->path);
            rmdir(dirname($this->path));
        }
    }

    public function test_falls_back_to_config_file_when_nothing_else_is_set(): void
    {
        $config = new ConfigRepository($this->path);
        $config->set('endpoint', 'https://file.example.com/api/v1');

        $settings = Settings::resolve($config);

        $this->assertSame('https://file.example.com/api/v1', $settings->endpoint);
    }

    public function test_env_var_overrides_config_file(): void
    {
        $config = new ConfigRepository($this->path);
        $config->set('endpoint', 'https://file.example.com/api/v1');

        putenv('FILESERVER_ENDPOINT=https://env.example.com/api/v1');

        $settings = Settings::resolve($config);

        $this->assertSame('https://env.example.com/api/v1', $settings->endpoint);
    }

    public function test_explicit_flag_overrides_everything(): void
    {
        $config = new ConfigRepository($this->path);
        $config->set('endpoint', 'https://file.example.com/api/v1');
        putenv('FILESERVER_ENDPOINT=https://env.example.com/api/v1');

        $settings = Settings::resolve($config, endpointFlag: 'https://flag.example.com/api/v1');

        $this->assertSame('https://flag.example.com/api/v1', $settings->endpoint);
    }

    public function test_all_three_fields_resolve_independently(): void
    {
        $config = new ConfigRepository($this->path);

        $settings = Settings::resolve($config, tokenFlag: 'flag-token', bucketFlag: 'flag-bucket');

        $this->assertNull($settings->endpoint);
        $this->assertSame('flag-token', $settings->token);
        $this->assertSame('flag-bucket', $settings->defaultBucket);
    }
}
