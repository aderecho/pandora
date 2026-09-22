<?php

namespace FileServerCli\Commands;

use FileServerCli\Support\ExitCode;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'delete', description: 'Delete <bucket>/<object-key>')]
class DeleteCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument('target', InputArgument::REQUIRED, '<bucket>/<object-key>');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $settings = $this->settings($input);

        if (! $this->requireEndpointAndToken($settings, $output)) {
            return ExitCode::USAGE_ERROR;
        }

        try {
            [$bucket, $key] = $this->splitBucketAndKey((string) $input->getArgument('target'));
        } catch (\InvalidArgumentException $e) {
            $this->writeError($output, $e->getMessage());

            return ExitCode::USAGE_ERROR;
        }

        try {
            $this->client($settings)->deleteObject($bucket, $key);
        } catch (\Throwable $e) {
            return $this->reportException($e, $input, $output);
        }

        if ($this->isJson($input)) {
            $this->outputJson($output, ['bucket' => $bucket, 'key' => $key, 'deleted' => true]);
        } else {
            $output->writeln("<info>Deleted</info> {$bucket}/{$key}");
        }

        return ExitCode::SUCCESS;
    }
}
