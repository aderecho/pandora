<?php

namespace FileServerCli;

use FileServerCli\Commands\BucketCreateCommand;
use FileServerCli\Commands\BucketListCommand;
use FileServerCli\Commands\ConfigListCommand;
use FileServerCli\Commands\ConfigSetCommand;
use FileServerCli\Commands\DeleteCommand;
use FileServerCli\Commands\DownloadCommand;
use FileServerCli\Commands\InfoCommand;
use FileServerCli\Commands\ListCommand;
use FileServerCli\Commands\MkdirCommand;
use FileServerCli\Commands\TokenCheckCommand;
use FileServerCli\Commands\UploadCommand;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

class Application extends SymfonyApplication
{
    public const string VERSION = '1.0.0';

    public function __construct()
    {
        parent::__construct('fileserver', self::VERSION);

        $this->addCommands([
            new ConfigSetCommand,
            new ConfigListCommand,
            new BucketListCommand,
            new BucketCreateCommand,
            new UploadCommand,
            new DownloadCommand,
            new ListCommand,
            new InfoCommand,
            new DeleteCommand,
            new MkdirCommand,
            new TokenCheckCommand,
        ]);
    }

    protected function getDefaultInputDefinition(): InputDefinition
    {
        $definition = parent::getDefaultInputDefinition();

        $definition->addOption(new InputOption(
            'json',
            null,
            InputOption::VALUE_NONE,
            'Output machine-readable JSON instead of formatted text'
        ));

        $definition->addOption(new InputOption(
            'endpoint',
            null,
            InputOption::VALUE_REQUIRED,
            'Override the configured API endpoint for this call'
        ));

        $definition->addOption(new InputOption(
            'token',
            null,
            InputOption::VALUE_REQUIRED,
            'Override the configured API token for this call'
        ));

        return $definition;
    }
}
