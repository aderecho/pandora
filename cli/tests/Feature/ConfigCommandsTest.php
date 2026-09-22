<?php

namespace FileServerCli\Tests\Feature;

use FileServerCli\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ConfigCommandsTest extends TestCase
{
    private string $home;

    protected function setUp(): void
    {
        $this->home = sys_get_temp_dir().'/fileserver_cli_feature_'.uniqid();
        mkdir($this->home, 0700, true);
        putenv("HOME={$this->home}");
    }

    protected function tearDown(): void
    {
        putenv('HOME');
        @unlink($this->home.'/.fileserver/config.json');
        @rmdir($this->home.'/.fileserver');
        @rmdir($this->home);
    }

    public function test_config_set_then_config_list_round_trip(): void
    {
        $app = new Application;
        $app->setAutoExit(false);

        $set = new CommandTester($app->find('config:set'));
        $set->execute(['key' => 'endpoint', 'value' => 'https://files.example.com/api/v1']);
        $this->assertSame(0, $set->getStatusCode());

        $list = new CommandTester($app->find('config:list'));
        $list->execute([]);
        $this->assertSame(0, $list->getStatusCode());
        $this->assertStringContainsString('https://files.example.com/api/v1', $list->getDisplay());
    }

    public function test_config_set_rejects_unknown_key(): void
    {
        $app = new Application;
        $app->setAutoExit(false);

        $set = new CommandTester($app->find('config:set'));
        $set->execute(['key' => 'bogus', 'value' => 'x']);

        $this->assertSame(2, $set->getStatusCode());
    }

    public function test_config_set_masks_token_in_list_output(): void
    {
        $app = new Application;
        $app->setAutoExit(false);

        (new CommandTester($app->find('config:set')))->execute(['key' => 'token', 'value' => 'fsk_super_secret_value']);

        $list = new CommandTester($app->find('config:list'));
        $list->execute([]);

        $this->assertStringNotContainsString('fsk_super_secret_value', $list->getDisplay());
    }

    public function test_json_output_mode(): void
    {
        $app = new Application;
        $app->setAutoExit(false);

        $set = new CommandTester($app->find('config:set'));
        $set->execute(['key' => 'default-bucket', 'value' => 'documents', '--json' => true]);

        $decoded = json_decode($set->getDisplay(), true);
        $this->assertSame('default-bucket', $decoded['key']);
        $this->assertTrue($decoded['saved']);
    }
}
