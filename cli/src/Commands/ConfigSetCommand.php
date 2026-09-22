<?php

namespace FileServerCli\Commands;

use FileServerCli\Config\ConfigRepository;
use FileServerCli\Support\ExitCode;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'config:set', description: 'Persist a CLI setting (endpoint, token, default-bucket)')]
class ConfigSetCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument('key', InputArgument::REQUIRED, 'endpoint | token | default-bucket')
            ->addArgument('value', InputArgument::REQUIRED, 'Value to store')
            ->setHelp(
                "Examples:\n".
                "  fileserver config:set endpoint https://files.example.com/api/v1\n".
                "  fileserver config:set token YOUR_CLIENT_TOKEN\n".
                '  fileserver config:set default-bucket documents'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $key = (string) $input->getArgument('key');
        $value = (string) $input->getArgument('value');

        if (! in_array($key, ConfigRepository::allowedKeys(), true)) {
            $this->writeError($output, 'Unknown key ['.$key.']. Allowed: '.implode(', ', ConfigRepository::allowedKeys()));

            return ExitCode::USAGE_ERROR;
        }

        $config = new ConfigRepository;
        $config->set($key, $value);

        if ($this->isJson($input)) {
            $this->outputJson($output, ['key' => $key, 'saved' => true]);
        } else {
            $output->writeln("<info>Saved</info> {$key} to {$config->path()}");
        }

        return ExitCode::SUCCESS;
    }
}
