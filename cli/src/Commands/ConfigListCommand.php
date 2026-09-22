<?php

namespace FileServerCli\Commands;

use FileServerCli\Config\ConfigRepository;
use FileServerCli\Support\ExitCode;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'config:list', description: 'Show the resolved CLI configuration (token masked)')]
class ConfigListCommand extends BaseCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $settings = $this->settings($input);
        $config = new ConfigRepository;

        $rows = [
            'endpoint' => $settings->endpoint,
            'token' => $this->mask($settings->token),
            'default-bucket' => $settings->defaultBucket,
        ];

        if ($this->isJson($input)) {
            $this->outputJson($output, array_merge($rows, ['config_file' => $config->path()]));

            return ExitCode::SUCCESS;
        }

        $output->writeln("Config file: {$config->path()}");
        foreach ($rows as $key => $value) {
            $output->writeln(sprintf('%-16s %s', $key, $value ?? '<comment>(not set)</comment>'));
        }

        return ExitCode::SUCCESS;
    }

    private function mask(?string $token): ?string
    {
        if ($token === null) {
            return null;
        }

        return strlen($token) > 8 ? substr($token, 0, 8).str_repeat('*', 8) : str_repeat('*', strlen($token));
    }
}
